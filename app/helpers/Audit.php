<?php

/**
 * Minimal audit-trail writer.
 *
 * This is a deliberate stand-in for the future AuditService (C-36 / UC-00c).
 * The signature and the columns it writes are the ones AuditService will use,
 * so the call sites do not change when the service is built: the body lifts
 * out of here and into AuditService::write() unchanged.
 *
 * Contract: a failed audit write is logged and swallowed. It must never abort
 * the surrounding operation — a successful login that fails to audit is still
 * a successful login.
 */
class Audit
{
    /**
     * @param int|null    $userId      users.user_id, or null for a pre-account event
     * @param string      $actionType  must be a value in the audit_logs.action_type enum
     * @param string      $description human-readable summary, <= 255 chars
     * @param string|null $entityType  e.g. 'user', 'employer'
     * @param int|null    $entityId    primary key of the affected entity
     */
    public static function write(
        $userId,
        $actionType,
        $description,
        $entityType = null,
        $entityId = null
    ) {
        try {
            $db = Database::getInstance()->getConnection();
            $stmt = $db->prepare(
                "INSERT INTO audit_logs
                    (user_id, action_type, entity_type, entity_id, description, ip_address, user_agent)
                 VALUES
                    (:user_id, :action_type, :entity_type, :entity_id, :description, :ip_address, :user_agent)"
            );
            $stmt->execute([
                ':user_id'     => $userId,
                ':action_type' => $actionType,
                ':entity_type' => $entityType,
                ':entity_id'   => $entityId,
                ':description' => mb_substr($description, 0, 255),
                ':ip_address'  => $_SERVER['REMOTE_ADDR'] ?? null,
                ':user_agent'  => isset($_SERVER['HTTP_USER_AGENT'])
                    ? mb_substr($_SERVER['HTTP_USER_AGENT'], 0, 255)
                    : null,
            ]);
        } catch (Throwable $e) {
            error_log('[audit] write failed: ' . $e->getMessage());
        }
    }
}
