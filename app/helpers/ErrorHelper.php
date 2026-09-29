<?php
/**
 * Helper: Human-Friendly Error Message Translator
 * Simplifies system error notifications into clear, easy-to-understand messages.
 */
class ErrorHelper
{
    private static array $messages = [
        // Authorization & Access
        'unauthorized'               => 'You do not have permission to access that area. Please log in with an authorized account.',
        'access_denied'              => 'Access denied. You do not have the required permissions for this action.',
        'access_denied_or_not_found' => 'The requested item could not be found or you do not have permission to view it.',
        'timeout'                    => 'Your session has timed out due to inactivity. Please sign in again.',

        // Data & Records
        'not_found'                  => 'The requested item could not be found.',
        'invalid_id'                 => 'Invalid item selected. Please try again from the list.',
        'invalid_employer'           => 'The employer company details could not be found.',
        'company_not_found'          => 'The requested company profile does not exist.',
        'invalid_application'        => 'The job application details could not be found.',
        'invalid_job'                => 'The job posting details could not be found.',

        // Form Validation & Status Transitions
        'invalid_status'             => 'The status update selected is invalid. Please choose a valid status option.',
        'invalid_request'            => 'The request could not be processed. Please refresh the page and try again.',
        'invalid_transition'         => 'This application status cannot be reverted backwards.',
        'invalid_data'               => 'Please ensure all required information is filled out correctly.',
        'missing_fields'             => 'Some required information was missing. Please review your entries and try again.',

        // Business Permits & Documents
        'no_permit_file'             => 'No business permit document file was found for this employer.',
        'ai_failed'                  => 'The AI Engine was temporarily unable to analyze this document. The permit remains queued for manual PESO review.',
        'database_error'             => 'A temporary system issue occurred. Please try again in a few moments.',
        'upload_failed'              => 'Document upload failed. Please ensure the file size is under 10MB and formatted as PDF, JPG, or PNG.',

        // Success Notices
        'verified'                   => 'Employer verification approved successfully!',
        'rejected'                   => 'Employer verification rejected. Associated live postings have been suspended.',
        'skill_approved'             => 'Master skill approved and added to taxonomy!',
        'skill_deleted'              => 'Custom skill suggestion removed.',
        'status_updated'             => 'Status updated successfully!',
        'skill_created'              => 'New master skill created successfully!',
        'ai_reanalyzed'              => 'AI Engine permit audit re-analysis completed!',
        'logged_out'                 => 'You have been logged out successfully.'
    ];

    /**
     * Translates a raw error or success code into a friendly, clear user notification message.
     */
    public static function translate(?string $code, string $defaultPrefix = ''): string
    {
        if (empty($code)) {
            return '';
        }

        $code = strtolower(trim($code));

        if (isset(self::$messages[$code])) {
            return self::$messages[$code];
        }

        // Fallback formatting for unlisted codes
        $cleaned = ucfirst(str_replace(['_', '-'], ' ', $code));
        return $defaultPrefix !== '' ? "{$defaultPrefix}: {$cleaned}" : $cleaned;
    }
}
