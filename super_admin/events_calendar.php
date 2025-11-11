<?php
// This part of the code can be at the top of events_calendar.php
session_start();
require_once "../database/config.php"; // Adjust path as needed

// Fetch all events to pass to the calendar
$events_json = [];
$sql = "SELECT id, title, start_date AS start, end_date AS end, color FROM events";
if ($result = mysqli_query($link, $sql)) {
    while ($row = mysqli_fetch_assoc($result)) {
        // FullCalendar needs specific keys: title, start, end, etc.
        $events_json[] = $row;
    }
}
mysqli_close($link);
// We will encode this PHP array into a JSON string for JavaScript to use
$events_for_calendar = json_encode($events_json);
require_once './admin_header.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>School Events Calendar</title>
    <!-- 1. FullCalendar CSS and JS from a CDN -->
    <script src='https://cdn.jsdelivr.net/npm/fullcalendar@6.1.9/index.global.min.js'></script>
    <style>
        body { font-family: 'Segoe UI', sans-serif; background-color: #f4f7f6; }
        .calendar-container { max-width: 1100px; margin: 40px auto; padding: 30px; background-color: #fff; border-radius: 10px; box-shadow: 0 4px 15px rgba(0,0,0,0.1); }
        /* Style the calendar header */
        .fc-header-toolbar { padding-bottom: 20px !important; }
        .fc-toolbar-title { font-size: 1.75em !important; color: #1a2c5a; }
        .fc-button { background-color: #007bff !important; border: none !important; }
        .fc-button-primary:hover { background-color: #0056b3 !important; }
        .fc-daygrid-event { padding: 5px; font-size: 0.85em; }
    </style>
</head>
<body>
    <div class="calendar-container">
        <div id='calendar'></div>
    </div>

    <script>
      document.addEventListener('DOMContentLoaded', function() {
        var calendarEl = document.getElementById('calendar');
        
        // 2. The PHP array of events is passed to JavaScript here
        var eventsData = <?php echo $events_for_calendar; ?>;

        var calendar = new FullCalendar.Calendar(calendarEl, {
          initialView: 'dayGridMonth',
          headerToolbar: {
            left: 'prev,next today',
            center: 'title',
            right: 'dayGridMonth,timeGridWeek,listWeek'
          },
          events: eventsData,
          eventDidMount: function(info) {
            // Optional: Add a tooltip to show description
            if (info.event.extendedProps.description) {
              // You can use a library like Tippy.js for better tooltips
              info.el.setAttribute('title', info.event.extendedProps.description);
            }
          }
        });
        calendar.render();
      });
    </script>
</body>
</html>
<?php
require_once './admin_footer.php';
?>