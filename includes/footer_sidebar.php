<?php
if (defined('APP_OUTPUT_BUFFER_STARTED') && APP_OUTPUT_BUFFER_STARTED && ob_get_level() > 0) {
	ob_end_flush();
}
?>
</body>
</html>
