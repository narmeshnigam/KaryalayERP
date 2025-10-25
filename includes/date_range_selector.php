<!-- /**
 * Date Range Selector Component
 * Reusable component for selecting date ranges with preset options
 * Usage: Include this file and call renderDateRangeSelector($from_date, $to_date, $show_label)
 */ -->

<?php
if (!function_exists('getDateRangePresets')) {
    function getDateRangePresets()
    {
        $today = date('Y-m-d');
        $yesterday = date('Y-m-d', strtotime('-1 day'));
        
        // Get financial year (April 1 to March 31)
        $current_month = (int) date('n');
        if ($current_month >= 4) {
            $fy_start = date('Y') . '-04-01';
            $fy_end = (date('Y') + 1) . '-03-31';
        } else {
            $fy_start = (date('Y') - 1) . '-04-01';
            $fy_end = date('Y') . '-03-31';
        }
        
        return [
            'today' => [
                'label' => 'Today',
                'from' => $today,
                'to' => $today
            ],
            'yesterday' => [
                'label' => 'Yesterday',
                'from' => $yesterday,
                'to' => $yesterday
            ],
            'this_week' => [
                'label' => 'This Week',
                'from' => date('Y-m-d', strtotime('monday this week')),
                'to' => date('Y-m-d', strtotime('sunday this week'))
            ],
            'last_week' => [
                'label' => 'Last Week',
                'from' => date('Y-m-d', strtotime('monday last week')),
                'to' => date('Y-m-d', strtotime('sunday last week'))
            ],
            'this_month' => [
                'label' => 'This Month',
                'from' => date('Y-m-01'),
                'to' => date('Y-m-t')
            ],
            'last_month' => [
                'label' => 'Last Month',
                'from' => date('Y-m-01', strtotime('first day of last month')),
                'to' => date('Y-m-t', strtotime('last day of last month'))
            ],
            'this_quarter' => [
                'label' => 'This Quarter',
                'from' => date('Y-m-01', strtotime('first day of ' . (ceil(date('n') / 3) * 3 - 2) . ' months')),
                'to' => date('Y-m-t', strtotime('last day of ' . (ceil(date('n') / 3) * 3) . ' months'))
            ],
            'this_year' => [
                'label' => 'This Year',
                'from' => date('Y-01-01'),
                'to' => date('Y-12-31')
            ],
            'last_year' => [
                'label' => 'Last Year',
                'from' => (date('Y') - 1) . '-01-01',
                'to' => (date('Y') - 1) . '-12-31'
            ],
            'this_fy' => [
                'label' => 'This Financial Year',
                'from' => $fy_start,
                'to' => $fy_end
            ],
            'custom' => [
                'label' => 'Custom Range',
                'from' => '',
                'to' => ''
            ]
        ];
    }
}

if (!function_exists('detectDateRangePreset')) {
    function detectDateRangePreset($from_date, $to_date)
    {
        $presets = getDateRangePresets();
        foreach ($presets as $key => $preset) {
            if ($preset['from'] === $from_date && $preset['to'] === $to_date) {
                return $key;
            }
        }
        return 'custom';
    }
}

if (!function_exists('renderDateRangeSelector')) {
    function renderDateRangeSelector($from_date, $to_date, $show_label = true, $extra_class = '')
    {
        $presets = getDateRangePresets();
        $selected_preset = detectDateRangePreset($from_date, $to_date);
        ?>
        <div class="date-range-selector <?php echo htmlspecialchars($extra_class, ENT_QUOTES); ?>">
            <?php if ($show_label) : ?>
                <label for="date_range_preset">Date Range</label>
            <?php endif; ?>
            <div style="display:grid;grid-template-columns:200px 1fr 1fr;gap:12px;align-items:end;">
                <div class="form-group" style="margin:0;">
                    <label for="date_range_preset" style="font-size:12px;color:#6c757d;">Quick Select</label>
                    <select class="form-control" name="date_range_preset" id="date_range_preset" onchange="handleDateRangePresetChange(this.value)">
                        <?php foreach ($presets as $key => $preset) : ?>
                            <option value="<?php echo htmlspecialchars($key, ENT_QUOTES); ?>" <?php echo ($key === $selected_preset) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($preset['label'], ENT_QUOTES); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group" style="margin:0;">
                    <label for="from_date" style="font-size:12px;color:#6c757d;">From Date</label>
                    <input type="date" class="form-control" name="from_date" id="from_date" value="<?php echo htmlspecialchars($from_date, ENT_QUOTES); ?>" onchange="handleCustomDateChange()">
                </div>
                <div class="form-group" style="margin:0;">
                    <label for="to_date" style="font-size:12px;color:#6c757d;">To Date</label>
                    <input type="date" class="form-control" name="to_date" id="to_date" value="<?php echo htmlspecialchars($to_date, ENT_QUOTES); ?>" onchange="handleCustomDateChange()">
                </div>
            </div>
        </div>

        <script>
            const dateRangePresets = <?php echo json_encode($presets); ?>;
            
            function handleDateRangePresetChange(preset) {
                if (preset === 'custom') {
                    return;
                }
                
                const presetData = dateRangePresets[preset];
                if (presetData) {
                    document.getElementById('from_date').value = presetData.from;
                    document.getElementById('to_date').value = presetData.to;
                }
            }
            
            function handleCustomDateChange() {
                document.getElementById('date_range_preset').value = 'custom';
            }
        </script>
        <?php
    }
}
?>
