<?php

return [
    'allowed' => [
        // Form CRUD
        'open.forms.index-all',
        'open.forms.store',
        'open.forms.show',
        'open.forms.update',
        'open.forms.destroy',

        // Form Submission CRUD
        'open.forms.submissions.index',
        'open.forms.submissions.update',
        'open.forms.submissions.export',
        'open.forms.submissions.destroy',

        // Form Integrations
        'open.forms.integrations.index',
        'open.forms.integrations.create',
        'open.forms.integrations.update',
        'open.forms.integrations.destroy',
        'open.forms.integrations.events',

        // Workspace CRUD
        'open.workspaces.index',
        'open.workspaces.create',
        'open.workspaces.update',
        'open.workspaces.delete',
        'open.workspaces.forms.index',

        // Workspace User Management
        'open.workspaces.users.index',
        'open.workspaces.users.add',
        'open.workspaces.users.remove',
        'open.workspaces.users.update-role',
        'open.workspaces.leave',
        'open.workspaces.invites.index',
        'open.workspaces.invites.resend',
        'open.workspaces.invites.cancel',
    ],
];
