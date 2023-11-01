
<form action="" method="GET" class="report-controls-form">
    <div class="form-group">
        <label for="sendAtStart">Start Date:</label>
        <input type="date" id="sendAtStart" name="sendAtStart" value="<?php echo $sendAtStart; ?>" class="form-control">
    </div>

    <div class="form-group">
        <label for="sendAtEnd">End Date:</label>
        <input type="date" id="sendAtEnd" name="sendAtEnd" value="<?php echo $sendAtEnd; ?>" class="form-control">
    </div>

    <div class="form-group">
        <label for="minSends">Min Sends:</label>
        <input type="number" id="minSends" name="minSends" value="<?php echo $minSends; ?>" class="form-control">
    </div>

    <div class="form-group">
        <label for="maxSends">Max Sends:</label>
        <input type="number" id="maxSends" name="maxSends" value="<?php echo $maxSends; ?>" class="form-control">
    </div>

    <div class="form-group">
        <label for="minMetric">Min Rate:</label>
        <input type="number" id="minMetric" name="minMetric" value="<?php echo $minMetric; ?>"
            class="form-control">
    </div>

    <div class="form-group">
        <label for="maxMetric">Max Rate:</label>
        <input type="number" id="maxMetric" name="maxMetric" value="<?php echo $maxMetric; ?>"
            class="form-control">
    </div>
    <div class="form-group">
        <button type="submit" class="wiz-button green">Update Chart</button>
    </div>
</form>