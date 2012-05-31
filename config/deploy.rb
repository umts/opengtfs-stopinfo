set :application, "gtfs-stops"
set :repository,  "set your repository location here"

set :scm, :git

role :web, "dieselnet.admin.umass.edu"                          # Your HTTP server, Apache/etc
role :app, "dieselnet.admin.umass.edu"                          # This may be the same as your `Web` server

set :deploy_to, "/srv/stop-info"
set :deploy_via, :export

set :server_user, "apache"
set :server_group, "wheel"

set :ssh_options, { :forward_agent => true, :port => 1022 }
default_run_options[:pty] = true

after "deploy:setup", "deploy:permissions", :dbconfig
after "deploy:update_code", "dbconfig:symlink"

# Overrides of default tasks:
namespace :deploy do
  desc "Deploys your project. For our non-rails app, this just calls `update'"
  task :default do
    update
  end

  desc "Alias for deploy.  A cold-deploy is the same as a 'warm' one for non-rails apps"
  task :cold do
    default
  end

  desc <<-DESC
    Rolls back to a previous version. This is handy if you ever \
    discover that you've deployed a lemon; `cap rollback' and you're right \
    back where you were, on the previously deployed version.
  DESC
  namespace :rollback do
    task :default do
      code
    end
  end

  desc <<-DESC
    [internal] Touches up the released code. This is called by update_code \
    after the basic deploy finishes.

    This task will make the release group-writable (if the :group_writable \
    variable is set to true, which is the default).
  DESC
  task :finalize_update, :except => { :no_release => true } do
    run "chmod -R g+w #{latest_release}" if fetch(:group_writable, true)
  end

  desc <<-DESC
    Prepares one or more servers for deployment. Before you can use any \
    of the Capistrano deployment tasks with your project, you will need to \
    make sure all of your servers have been prepared with `cap deploy:setup'. When \
    you add a new server to your cluster, you can easily run the setup task \
    on just that server by specifying the HOSTS environment variable:

      $ cap HOSTS=new.server.com deploy:setup

    It is safe to run this task on servers that have already been set up; it \
    will not destroy any deployed revisions or data.
  DESC
  task :setup, :except => { :no_release => true } do
    dirs = [deploy_to, releases_path, shared_path]
    run "umask 02 && mkdir -p #{dirs.join(' ')}"
  end

  desc <<-DESC
    Gives a quick ls -l of the releases directory, for purposes of checking up \
    on the frequency / blame / overall volume of deployments.
  DESC
  task :list_releases, :roles => :web do
    run "ls -l #{deploy_to}/releases/"
  end

  #Tasks available by default with Capistrano that don't do anything for us
  task :migrate do
  end
  task :migrations do
  end
  task :restart do
  end
  task :start do
  end
  task :stop do
  end

  desc <<-DESC
    Fixes directory permissions - run after setup.  This task trys to set \
    ownership of the deploy path to server_user:server_group and then sets \
    the permissions to at least rw-r-S---.

    If group_writable is set, add that.
  DESC
  task :permissions do
    run "#{try_sudo} chown -R #{server_user}:#{server_group} #{deploy_to} && #{try_sudo} chmod -R g+s #{deploy_to}"
    run "#{try_sudo} chmod -R g+w #{deploy_to}" if fetch(:group_writable, true)
  end
end

namespace :dbconfig do

  set(:db_host) do
    Capistrano::CLI.password_prompt("Enter the hostname for the opengtfs database server :")
  end
  set(:db_user) do
    Capistrano::CLI.password_prompt("Enter the database username :")
  end
  set(:db_password) do
    Capistrano::CLI.password_prompt("Enter the password for database user #{db_user} :")
  end

  desc "Create database config in shared path"
  task :default do
    db_config = ERB.new <<-EOF
<?php
define( 'DB_NAME', 'gtfs_production' );
define( 'DB_HOSTNAME', '#{db_host}' );
define( 'DB_USERNAME', '#{db_user}' );
define( 'DB_PASSWORD', '#{db_password}' );

define( 'LOG_DB_NAME', 'qr_log' );
define( 'LOG_DB_HOSTNAME', DB_HOSTNAME );
define( 'LOG_DB_USERNAME', DB_USERNAME );
define( 'LOG_DB_PASSWORD', DB_PASSWORD );
?>
    EOF
    run "mkdir -p #{shared_path}/config"
    put db_config.result, "#{shared_path}/config/database.php", :mode => '660'
  end

  desc "Make symlink for database config file"
  task :symlink do
    run "ln -nfs #{shared_path}/config/database.php #{release_path}/config/database.php"
  end

end
# vim: set filetype=ruby :

