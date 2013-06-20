What is this?
=============
This application was built by [UMass Transit][umts] for the [PVTA][pvta] to
supplement the mobile schedules application, [OpenGTFS][opengtfs], which
is hosted at http://m.pvta.com  It connects to the MySQL database back-end
that OpenGTFS uses and provides scheduled bus arrivals for a given stop id.

The urls for each stop were then each encoded in a QR-code and posted at their
respective bus stops.

Why?
====
"Why didn't you just add this functionality to OpenGTFS?"  A reasonable
question, but the developer resources available to us at the time were
pretty concentrated on PHP, so that's what we used.

It might seem a little weird, but what we ended up with is a small, simple,
self-contained application that serves it's one function tremendously well.

Configuration
=============
This application needs `SELECT` permission on your OpenGTFS database.  It
would probably be a good idea to create a separate database user for this
purpose.

In addition, it connects to a separate logging database.  The
file `log_db.sql` contains the `CREATE TABLE`... neded to create the one
required table.

Future Plans
============
Predictive times are in our long-term plans, but we don't yet have a
good way to get that information from our radio system. Stay tuned.


[umts]: http://www.umass.edu/transit/
[pvta]: http://www.pvta.com/
[opengtfs]: https://github.com/umts/openmbta
