<?php
/** Legacy entry point — the admin panel is now under the user menu. */
require __DIR__ . '/inc/bootstrap.php';
redirect(es_can('users.manage') ? 'admin_users.php'
       : (es_can('rbac.manage') ? 'admin_roles.php'
       : (es_can('refdata.manage') ? 'admin_refdata.php' : 'index.php')));
