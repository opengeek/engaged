<?php
/**
 * @package rpx
 * @subpackage user.mysql
 */
require_once (strtr(realpath(dirname(dirname(__FILE__))), '\\', '/') . '/rpxuser.class.php');
class rpxUser_mysql extends rpxUser {}
