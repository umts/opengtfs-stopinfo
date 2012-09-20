<?php
ob_start();
include './config/database.php';
ob_end_clean();

//print "System unavailable"; exit(1);

if(!(isset($_REQUEST['stop']) && ( is_int($_REQUEST['stop']) || ctype_digit($_REQUEST['stop']) )))
{
    print "Unknown bus stop.";
    exit(1);
}

$stopId = $_REQUEST['stop'];

$stop = getStop($stopId);

if(!$stop)
{
    print "Unknown bus stop!";
    exit(1);
}


$stopName = $stop['name'];

$nextBuses = getNextBuses($stopId);

logRequest($_REQUEST['stop'], $_SERVER['REMOTE_ADDR'], $_SERVER['HTTP_USER_AGENT']);



function nextBusesToTable($nextBuses, $stopName)
{
    $result = "<div class=\"stopName\">" . $stopName . "</div>\n";

    if(!$nextBuses)
    {
        $result .= "No buses scheduled!";
    }

    else
    {
        $result .= "<table class=\"nextbuses\">\n";

        $even = true;
        foreach($nextBuses as $currentBus)
        {
            $result .= "\t<tr class=\"" . ($even ? 'even' : 'odd') . "\">\n";
            $result .= "\t\t<td class=\"departuretime\">" . trim(strftime('%l:%M%P', strtotime($currentBus['departure_date_time']))) . "</td>\n";
            $result .= "\t\t<td class=\"routeNumber route-" . $currentBus['short_name'] . "\">" . $currentBus['short_name'] . "</td>\n";
            $result .= "\t\t<td class=\"headsign\">" . $currentBus['headsign'] . "</td>\n";
            $result .="\t</tr>\n";
            $even = !$even;
        }
        $result .= "</table>\n";
    }
    return $result;
}


function getNextBuses($stopID)
{
    $dbConnection = mysql_connect(DB_HOSTNAME, DB_USERNAME, DB_PASSWORD);

    if(!$dbConnection)
    {
        print "System unavailable.";
        exit(1);
    }

    if (!mysql_select_db(DB_NAME, $dbConnection)) {
        echo 'System unavailable.';
        exit;
    }

    $sql = "
SELECT name,
       short_name,
       long_name, headsign,
       min(CONCAT(IF(departure_time >= '24:00:00', DATE_ADD(date, INTERVAL 1 DAY), date),
                  ' ',
                  IF(departure_time >= '24:00:00', SUBTIME(departure_time, '24:00:00'), departure_time)
                 )
          ) as departure_date_time

FROM
((SELECT stops.name, routes.short_name, routes.long_name, trips.headsign, stoppings.departure_time, trips.gtfs_id, CURRENT_DATE() as date
FROM `services`
JOIN trips ON services.id = trips.service_id
JOIN stoppings ON trips.id = stoppings.trip_id
JOIN routes ON trips.route_id = routes.id
JOIN stops ON stoppings.stop_id = stops.id

WHERE
 ((monday    = '1' AND WEEKDAY(CURRENT_DATE()) = 0)
    OR
  (tuesday   = '1' AND WEEKDAY(CURRENT_DATE()) = 1)
    OR
  (wednesday = '1' AND WEEKDAY(CURRENT_DATE()) = 2)
    OR
  (thursday  = '1' AND WEEKDAY(CURRENT_DATE()) = 3)
    OR
  (friday    = '1' AND WEEKDAY(CURRENT_DATE()) = 4)
    OR
  (saturday  = '1' AND WEEKDAY(CURRENT_DATE()) = 5)
    OR
  (sunday    = '1' AND WEEKDAY(CURRENT_DATE()) = 6))

 AND
 CURRENT_DATE() BETWEEN `start_date` AND `end_date`
 AND
 ((services.id NOT IN
   (SELECT service_id
    FROM service_exceptions
    WHERE date = CURRENT_DATE() AND
          exception_type = 2))
  OR 
  services.id IN
    (SELECT service_id
     FROM service_exceptions
     WHERE date = CURRENT_DATE() AND
           exception_type = 1))
 AND
 departure_time > CURRENT_TIME()
 AND
 stops.gtfs_id = " . mysql_real_escape_string($stopID) . "
 AND
 stoppings.position != (SELECT MAX(position)
                              FROM stoppings
                              WHERE trip_id = trips.id)
)
UNION
(SELECT stops.name, short_name, long_name, trips.headsign, stoppings.departure_time, trips.gtfs_id, CURRENT_DATE() as date
FROM `services`
JOIN trips ON services.id = trips.service_id
JOIN stoppings ON trips.id = stoppings.trip_id
JOIN routes ON trips.route_id = routes.id
JOIN stops ON stoppings.stop_id = stops.id

WHERE
 ((monday    = '1' AND WEEKDAY(CURRENT_DATE()) = 1)
    OR
  (tuesday   = '1' AND WEEKDAY(CURRENT_DATE()) = 2)
    OR
  (wednesday = '1' AND WEEKDAY(CURRENT_DATE()) = 3)
    OR
  (thursday  = '1' AND WEEKDAY(CURRENT_DATE()) = 4)
    OR
  (friday    = '1' AND WEEKDAY(CURRENT_DATE()) = 5)
    OR
  (saturday  = '1' AND WEEKDAY(CURRENT_DATE()) = 6)
    OR
  (sunday    = '1' AND WEEKDAY(CURRENT_DATE()) = 0))

 AND
 DATE_SUB(CURRENT_DATE(), INTERVAL 1 DAY) BETWEEN `start_date` AND `end_date`
 AND
 ((services.id NOT IN
   (SELECT service_id
    FROM service_exceptions
    WHERE date = DATE_SUB(CURRENT_DATE(), INTERVAL 1 DAY) AND
          exception_type = 2))
  OR 
   services.id IN
    (SELECT service_id
     FROM service_exceptions
     WHERE date = DATE_SUB(CURRENT_DATE(), INTERVAL 1 DAY) AND
           exception_type = 1))
 AND
  stoppings.departure_time > ADDTIME(CURRENT_TIME(), '24:00:00')
 AND
  stops.gtfs_id = " . mysql_real_escape_string($stopID) . "
 AND
  stoppings.position != (SELECT MAX(position)
                              FROM stoppings
                              WHERE trip_id = trips.id))
) as abc
GROUP BY name, short_name, long_name, headsign
ORDER BY departure_date_time, short_name
";
//print $sql;

    $result = mysql_query($sql, $dbConnection);

    if (!$result) {
        echo "DB Error, could not query the database\n";
        echo 'MySQL Error: ' . mysql_error();
        exit;
    }

    if(mysql_num_rows($result) == 0)
        return false;

    $nextBuses = array();

    while($row = mysql_fetch_assoc($result))
        $nextBuses[] = $row;
   
    mysql_free_result($result);

    return $nextBuses;
}


function getStop($stopID)
{
    $dbConnection = mysql_connect(DB_HOSTNAME, DB_USERNAME, DB_PASSWORD);

    if(!$dbConnection)
    {
        print "System unavailable.";
        exit(1);
    }

    if (!mysql_select_db(DB_NAME, $dbConnection)) {
        echo "System unavailable.";
        exit;
    }

    $sql = "SELECT * FROM stops WHERE gtfs_id = " . mysql_real_escape_string($stopID) . " LIMIT 1";


    $result = mysql_query($sql, $dbConnection);

    if (!$result) {
        print "System unavailable.";
        exit;
    }

    if(mysql_num_rows($result) != 1)
        return false;


    $row = mysql_fetch_assoc($result);

    mysql_free_result($result);

    return $row;
}



function logRequest($stopID, $ip, $browser)
{
    $dbConnection = mysql_connect(LOG_DB_HOSTNAME, LOG_DB_USERNAME, LOG_DB_PASSWORD);

    if(!$dbConnection)
    {
        exit(1);
    }

    if (!mysql_select_db(LOG_DB_NAME, $dbConnection))
    {
        print '';
        //echo 'Could not select database';
        //exit;
    }

    $sql = sprintf("INSERT INTO log (stop_id, ip, browser) VALUES (%s, %s, %s)",
        mysql_real_escape_string($stopID),
        ($ip ? "'" . mysql_real_escape_string($ip) . "'" : "null"),
        ($ip ? "'" . mysql_real_escape_string($browser) . "'" : "null"));


    $result = mysql_query($sql, $dbConnection);

    if (!$result)
    {
        print '';
        //echo "DB Error, could not query the database\n";
        //echo 'MySQL Error: ' . mysql_error();
        exit;
    }

    if(mysql_affected_rows($dbConnection) == 1)
        return true;
    else
        return false;
}

?>
<!doctype html>
<html>
  <head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="style.css">
    <title>PVTA - Next Scheduled Buses - <?= $stopName?></title>
  </head>
  <body>
<?= nextBusesToTable($nextBuses, $stopName)?>
  </body>
</html>






