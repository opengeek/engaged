<?php
/**
 * @package rpx
 * @subpackage user
 */
class rpxUser extends modUser {
    public function __construct(xPDO &$xpdo) {
        parent::__construct($xpdo);
        $this->set('class_key', 'rpxUser');
    }
}
