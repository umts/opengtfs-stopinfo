set :application, "gtfs-stops"
set :repository,  "git://github.com/umts/opengtfs-stopinfo.git"

set :scm, :git

role :web, "dieselnet.admin.umass.edu"                          # Your HTTP server, Apache/etc
role :app, "dieselnet.admin.umass.edu"                          # This may be the same as your `Web` server

set :deploy_to, "/srv/#{application}"
set :deploy_via, :export

set :server_user, "apache"
set :server_group, "wheel"

set :ssh_options, { :forward_agent => true, :port => 1022 }
default_run_options[:pty] = true

set :shared_children,   %w(log config)

after "deploy:setup", "deploy:permissions", :dbconfig
after "deploy:update_code", "dbconfig:symlink"

namespace :deploy do
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
    Capistrano::CLI.ui.ask("Enter the hostname for the opengtfs database server :")
  end
  set(:db_user) do
    Capistrano::CLI.ui.ask("Enter the database username :")
  end
  set(:db_password) do
    Capistrano::CLI.password_prompt("Enter the password for database user #{db_user} :")
  end

  desc "Create database config in shared path"
  task :default do
    db_config = ERB.new <<-EOF
<?php
define( 'DB_NAME', 'opengtfs_production' );
define( 'DB_HOSTNAME', '#{db_host}' );
define( 'DB_USERNAME', '#{db_user}' );
define( 'DB_PASSWORD', '#{db_password}' );

define( 'LOG_DB_NAME', 'qr_log' );
define( 'LOG_DB_HOSTNAME', DB_HOSTNAME );
define( 'LOG_DB_USERNAME', DB_USERNAME );
define( 'LOG_DB_PASSWORD', DB_PASSWORD );
?>
    EOF
    put db_config.result, "#{shared_path}/config/database.php", :mode => '660'
  end

  desc "Make symlink for database config file"
  task :symlink do
    run "#{try_sudo} ln -nfs #{shared_path}/config/database.php #{release_path}/config/database.php"
  end

end
