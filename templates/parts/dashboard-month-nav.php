<?php
if (isset($_GET['view']) && $_GET['view'] != 'FY' || !isset($_GET['view'])) {
    // Get the current month and year from the query parameters or use the current date as default
    $currentMonth = isset($_GET['startDate']) ? (new DateTime($_GET['startDate']))->format('m') : date('m');
    $currentYear = isset($_GET['startDate']) ? (new DateTime($_GET['startDate']))->format('Y') : date('Y');

    // Calculate the previous and next month and year
    $prevDate = new DateTime("{$currentYear}-{$currentMonth}-01");
    $prevDate->modify('-1 month');
    $nextDate = new DateTime("{$currentYear}-{$currentMonth}-01");
    $nextDate->modify('+1 month');

    $prevMonth = $prevDate->format('m');
    $prevYear = $prevDate->format('Y');
    $nextMonth = $nextDate->format('m');
    $nextYear = $nextDate->format('Y');

    // Disable the right arrow if the next month would be in the future
    $disableRightArrow = ($nextYear == date('Y') && $nextMonth > date('m')) || ($nextYear > date('Y'));
    ?>

    <div id="dashboardDateNav">
        <div class="wizDateNav-left">
            <a href="<?php echo esc_url(add_query_arg(array('startDate' => "{$prevYear}-{$prevMonth}-01", 'endDate' => $prevDate->format('Y-m-t')))); ?>"><i class="fa-solid fa-square-caret-left"></i></a>
        </div>
        <div class="wizDateNav-title">
            <!-- Month Dropdown -->
            <select id="wizMonthDropdown">
                <?php for ($i = 1; $i <= 12; $i++): ?>
                    <option value="<?php echo $i; ?>" <?php if ($i == $currentMonth) echo 'selected'; ?>>
                        <?php echo DateTime::createFromFormat('!m', $i)->format('F'); ?>
                    </option>
                <?php endfor; ?>
            </select>
            
            <!-- Year Dropdown -->
            <select id="wizYearDropdown">
                <?php for ($i = 2021; $i <= date('Y'); $i++): ?>
                    <option value="<?php echo $i; ?>" <?php if ($i == $currentYear) echo 'selected'; ?>>
                        <?php echo $i; ?>
                    </option>
                <?php endfor; ?>
            </select>
        </div>
        <div class="wizDateNav-right">
            <?php if (!$disableRightArrow): ?>
                <a href="<?php echo esc_url(add_query_arg(array('startDate' => "{$nextYear}-{$nextMonth}-01", 'endDate' => $nextDate->format('Y-m-t')))); ?>"><i class="fa-solid fa-square-caret-right"></i></a>
            <?php else: ?>
                <span><i class="fa-solid fa-square-caret-right disabled"></i></span>
            <?php endif; ?>
        </div>
    </div>

<?php 
} elseif (isset($_GET['view']) && $_GET['view'] === 'FY') {
?>

<div id="dashboardDateNav">
    <div class="wizDateNav-title">
        <h1>Current Fiscal Year</h1>
    </div>
</div>

<?php
}
?>
