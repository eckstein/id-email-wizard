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
    ?>

    <div id="dashboardDateNav">
        <div class="wizDateNav-left">
            <a href="?wizMonth=<?php echo $prevMonth; ?>&wizYear=<?php echo $prevYear; ?>"><i class="fa-solid fa-square-caret-left"></i></a>
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
                <a href="?wizMonth=<?php echo $nextMonth; ?>&wizYear=<?php echo $nextYear; ?>"><i class="fa-solid fa-square-caret-right"></i></a>
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

