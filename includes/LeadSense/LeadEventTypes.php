<?php
/**
 * Lead Event Types Constants
 * 
 * Defines all valid event types for the lead_events table.
 * Includes both existing LeadSense events and new CRM-specific events.
 * 
 * @package LeadSense
 */
class LeadEventTypes {
    // Existing LeadSense event types
    const DETECTED = 'detected';
    const UPDATED = 'updated';
    const QUALIFIED = 'qualified';
    const NOTIFIED = 'notified';
    const SYNCED = 'synced';
    const NOTE = 'note';
    
    // CRM-specific event types (Task 3)
    const STAGE_CHANGED = 'stage_changed';
    const OWNER_CHANGED = 'owner_changed';
    const PIPELINE_CHANGED = 'pipeline_changed';
    const DEAL_UPDATED = 'deal_updated';
    
    /**
     * Get all valid event types
     * 
     * @return array List of all valid event type constants
     */
    public static function all() {
        return [
            self::DETECTED,
            self::UPDATED,
            self::QUALIFIED,
            self::NOTIFIED,
            self::SYNCED,
            self::NOTE,
            self::STAGE_CHANGED,
            self::OWNER_CHANGED,
            self::PIPELINE_CHANGED,
            self::DEAL_UPDATED
        ];
    }
    
    /**
     * Check if an event type is valid
     * 
     * @param string $type Event type to validate
     * @return bool True if valid, false otherwise
     */
    public static function isValid($type) {
        return in_array($type, self::all(), true);
    }
    
    /**
     * Get existing (non-CRM) event types
     * 
     * @return array List of original LeadSense event types
     */
    public static function getExisting() {
        return [
            self::DETECTED,
            self::UPDATED,
            self::QUALIFIED,
            self::NOTIFIED,
            self::SYNCED,
            self::NOTE
        ];
    }
    
    /**
     * Get CRM-specific event types
     * 
     * @return array List of CRM event types
     */
    public static function getCRM() {
        return [
            self::STAGE_CHANGED,
            self::OWNER_CHANGED,
            self::PIPELINE_CHANGED,
            self::DEAL_UPDATED
        ];
    }
    
    /**
     * Get human-readable label for an event type
     * 
     * @param string $type Event type constant
     * @return string Human-readable label
     */
    public static function getLabel($type) {
        $labels = [
            self::DETECTED => 'Lead Detected',
            self::UPDATED => 'Lead Updated',
            self::QUALIFIED => 'Lead Qualified',
            self::NOTIFIED => 'Notification Sent',
            self::SYNCED => 'Synced',
            self::NOTE => 'Note Added',
            self::STAGE_CHANGED => 'Stage Changed',
            self::OWNER_CHANGED => 'Owner Changed',
            self::PIPELINE_CHANGED => 'Pipeline Changed',
            self::DEAL_UPDATED => 'Deal Updated'
        ];
        
        return $labels[$type] ?? $type;
    }
    
    /**
     * Get icon/emoji for an event type (for UI display)
     * 
     * @param string $type Event type constant
     * @return string Icon or emoji
     */
    public static function getIcon($type) {
        $icons = [
            self::DETECTED => '🔍',
            self::UPDATED => '✏️',
            self::QUALIFIED => '✅',
            self::NOTIFIED => '🔔',
            self::SYNCED => '🔄',
            self::NOTE => '📝',
            self::STAGE_CHANGED => '➡️',
            self::OWNER_CHANGED => '👤',
            self::PIPELINE_CHANGED => '🔀',
            self::DEAL_UPDATED => '💰'
        ];
        
        return $icons[$type] ?? '📌';
    }
}
