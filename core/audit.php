<?php
if (!defined('SF_ROOT')) { http_response_code(403); exit('Direct access denied'); }
/** Audit log + notifications + announcements (features 59, 60, 65). */
function audit($action, $target = '', $sev = 'info', $actor = null) {
    if (!db()) return;
    try {
        db_exec('INSERT INTO audit (user_id,actor,action,target,ip,sev,created_at) VALUES (?,?,?,?,?,?,?)', array(
            auth_id(), $actor !== null ? $actor : (auth_user() ? auth_user()['email'] : 'system'),
            $action, (string) $target, sf_ip(), $sev, db_now()
        ));
    } catch (Exception $e) {}
}
function notify($user_id, $type, $title, $body = '') {
    if (!db()) return;
    try { db_exec('INSERT INTO notifications (user_id,type,title,body,created_at) VALUES (?,?,?,?,?)',
        array((int) $user_id, $type, $title, $body, db_now())); } catch (Exception $e) {}
}
function notifications_unread($user_id) {
    return (int) db_val('SELECT COUNT(*) FROM notifications WHERE user_id=? AND `read`=0', array((int) $user_id), 0);
}
function announcements_active($limit = 3) {
    if (!db()) return array();
    try { return db_all('SELECT * FROM announcements ORDER BY created_at DESC LIMIT ' . (int) $limit); }
    catch (Exception $e) { return array(); }
}
