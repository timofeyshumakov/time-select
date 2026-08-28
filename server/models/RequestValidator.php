<?php
class RequestValidator {
    public function validateBitrix24Data($data) {
        if (empty($data['event'])) {
            throw new Exception('Missing event in Bitrix24 data');
        }
        
        if (empty($data['data'])) {
            throw new Exception('Missing data in Bitrix24 webhook');
        }
        
        return true;
    }
    
    public function validateRenovatioData($data) {
        if (empty($data['action'])) {
            throw new Exception('Missing action in Renovatio data');
        }
        
        return true;
    }
}