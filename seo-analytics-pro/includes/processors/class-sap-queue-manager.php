<?php
class SAP_Queue_Manager {
    public function add_to_queue($item) {
        return array('status' => 'queued');
    }
}
