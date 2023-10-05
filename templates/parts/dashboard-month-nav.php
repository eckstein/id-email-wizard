<?php
if (isset($_GET['view']) && $_GET['view'] != 'FY' || !isset($_GET['view'])) {
// Get the current month and year from the query parameters or use the current date as default
$currentMonth = isset($_GET['wizMonth']) ? intval($_GET['wizMonth']) : date('m');
$currentYear = isset($_GET['wizYear']) ? intval($_GET['wizYear']) : date('Y');


// Calculate the previous and next month and year
$prevMonth = $currentMonth - 1;
$prevYear = $currentYear;
$nextMonth = $currentMonth + 1;
$nextYear = $currentYear;

if ($prevMonth <= 0) {
    $prevMonth = 12;
    $prevYear -= 1;
}

if ($nextMonth > 12) {
    $nextMonth = 1;
    $nextYear += 1;
}

// Disable the right arrow if the next month would be in the future
$disableRightArrow = ($nextYear == date('Y') && $nextMonth > date('m')) || ($nextYear > date('Y'));

// Build the URLs
$prevUrl = "?wizMonth={$prevMonth}&wizYear={$prevYear}";
$nextUrl = "?wizMonth={$nextMonth}&wizYear={$nextYear}";

// Convert numeric month to its full textual representation
$fullMonthName = DateTime::createFromFormat('!m', $currentMonth)->format('F');
?>

<div id="dashboardDateNav">
    <div class="dateNav-left">
        <a href="<?php echo $prevUrl; ?>"><i class="fa-solid fa-square-caret-left"></i></a>
    </div>
    <div class="dateNav-title">
        <h1><?php echo "{$fullMonthName} {$currentYear}"; ?></h1>
    </div>
    <div class="dateNav-right">
        <?php if (!$disableRightArrow): ?>
            <a href="<?php echo $nextUrl; ?>"><i class="fa-solid fa-square-caret-right"></i></a>
        <?php else: ?>
            <span><i class="fa-solid fa-square-caret-right disabled"></i></span>
        <?php endif; ?>
    </div>
</div>
<?php 
} elseif (isset($_GET['view']) && $_GET['view'] === 'FY') {
?>
<div id="dashboardDateNav">

    <div class="dateNav-title">
        <h1>Current Fiscal Year</h1>
    </div>
    
</div>
<?php
}