<?php
/* Drop sessions whose user disappeared, was disabled or changed password. */
if (isset($_SESSION['user_logged_in']) && !is_logged_in()) {
    session_destroy();
}
