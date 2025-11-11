<?php
session_start();
require_once "../database/config.php";

// --- AUTHENTICATION & SECURITY ---
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true || $_SESSION["role"] !== 'Student') {
    header("location: ../login.php");
    exit;
}

// --- Fetch all events and process them ---
$upcoming_events = [];
$past_events = [];
$now = new DateTime(); // Get the current time

$sql = "SELECT id, title, description, start_date, end_date, event_type, color 
        FROM events 
        ORDER BY start_date ASC"; // Fetch all events, sorted by start date

if ($result = mysqli_query($link, $sql)) {
    while ($row = mysqli_fetch_assoc($result)) {
        $event_start_date = new DateTime($row['start_date']);
        
        // Compare the event's start date with the current time
        if ($event_start_date >= $now) {
            $upcoming_events[] = $row; // If event is in the future or happening now
        } else {
            $past_events[] = $row; // If event is in the past
        }
    }
}

// Reverse the past events array so the most recent past event is at the top
$past_events = array_reverse($past_events);

require_once './student_header.php';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>School Events</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body class="bg-gray-100 font-sans pt-20">

<div class="container mx-auto mt-28 max-w-4xl p-4 sm:p-6">
    <div class="text-center mb-10">
        <h1 class="text-4xl font-bold text-gray-800 tracking-tight">School Events</h1>
        <p class="text-gray-600 mt-2 text-lg">Upcoming holidays, exams, and school activities.</p>
    </div>

    <!-- Upcoming Events Section -->
    <div class="mb-12">
        <h2 class="text-2xl font-bold text-gray-700 border-b-2 border-blue-500 pb-2 mb-6 flex items-center gap-3">
            <i class="fas fa-calendar-alt text-blue-500"></i>
            Upcoming Events
        </h2>
        <div class="space-y-6">
            <?php if (empty($upcoming_events)): ?>
                <div class="text-center py-10 px-6 bg-white rounded-xl shadow-sm">
                    <p class="text-gray-500">There are no upcoming events scheduled at this time.</p>
                </div>
            <?php else: ?>
                <?php foreach ($upcoming_events as $event): ?>
                    <?php
                        $start_time = strtotime($event['start_date']);
                        $end_time = $event['end_date'] ? strtotime($event['end_date']) : null;
                        $event_color = $event['color'] ?? '#3b82f6'; // Default to blue
                    ?>
                    <div class="bg-white rounded-xl shadow-md hover:shadow-lg transition-shadow duration-300 flex overflow-hidden" style="border-left: 5px solid <?php echo htmlspecialchars($event_color); ?>;">
                        <!-- Date Block -->
                        <div class="flex-shrink-0 w-24 text-center bg-gray-50 p-4 flex flex-col justify-center items-center border-r">
                            <span class="text-blue-600 font-bold text-sm uppercase"><?php echo date('M', $start_time); ?></span>
                            <span class="text-gray-800 font-extrabold text-4xl tracking-tight"><?php echo date('d', $start_time); ?></span>
                            <span class="text-gray-500 font-semibold text-sm"><?php echo date('Y', $start_time); ?></span>
                        </div>
                        <!-- Details Block -->
                        <div class="p-5 flex-grow">
                            <h3 class="font-bold text-xl text-gray-900"><?php echo htmlspecialchars($event['title']); ?></h3>
                            <div class="text-sm text-gray-500 font-semibold mt-1 flex items-center gap-4">
                                <span><i class="fas fa-clock mr-1.5"></i> 
                                    <?php echo date('g:i A', $start_time); ?>
                                    <?php if ($end_time): echo ' - ' . date('g:i A', $end_time); endif; ?>
                                </span>
                                <span class="capitalize"><i class="fas fa-tag mr-1.5"></i> <?php echo htmlspecialchars($event['event_type']); ?></span>
                            </div>
                            <?php if (!empty($event['description'])): ?>
                                <p class="text-gray-700 mt-3 text-base leading-relaxed">
                                    <?php echo nl2br(htmlspecialchars($event['description'])); ?>
                                </p>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

    <!-- Past Events Section -->
    <div>
        <h2 class="text-2xl font-bold text-gray-700 border-b-2 border-gray-400 pb-2 mb-6 flex items-center gap-3">
            <i class="fas fa-history text-gray-400"></i>
            Past Events
        </h2>
        <div class="space-y-4">
            <?php if (empty($past_events)): ?>
                <div class="text-center py-10 px-6 bg-white rounded-xl shadow-sm opacity-75">
                    <p class="text-gray-500">No past events to show.</p>
                </div>
            <?php else: ?>
                <?php foreach (array_slice($past_events, 0, 5) as $event): // Show only the 5 most recent past events ?>
                    <?php
                        $start_time = strtotime($event['start_date']);
                        $event_color = $event['color'] ?? '#6b7280'; // Default to gray
                    ?>
                    <div class="bg-white rounded-xl shadow-sm flex overflow-hidden opacity-75" style="border-left: 5px solid <?php echo htmlspecialchars($event_color); ?>;">
                        <div class="flex-shrink-0 w-20 text-center bg-gray-50 p-3 flex flex-col justify-center items-center border-r">
                            <span class="text-gray-500 font-bold text-xs uppercase"><?php echo date('M', $start_time); ?></span>
                            <span class="text-gray-700 font-bold text-2xl"><?php echo date('d', $start_time); ?></span>
                        </div>
                        <div class="p-4 flex-grow">
                            <h3 class="font-semibold text-lg text-gray-700"><?php echo htmlspecialchars($event['title']); ?></h3>
                            <p class="text-sm text-gray-500 capitalize"><?php echo htmlspecialchars($event['event_type']); ?></p>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
</div>

</body>
</html>
<?php require_once './student_footer.php'; ?>