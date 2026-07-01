<?php

use App\Mcp\Resources\FieldTypesResource;
use App\Mcp\Servers\OpnFormServer;
use App\Mcp\Tools\Forms\CreateFormTool;
use App\Mcp\Tools\Forms\DeleteFormTool;
use App\Mcp\Tools\Forms\DuplicateFormTool;
use App\Mcp\Tools\Forms\GetFormTool;
use App\Mcp\Tools\Forms\ListFormsTool;
use App\Mcp\Tools\Forms\UpdateFormTool;
use App\Mcp\Tools\Workspaces\ListWorkspacesTool;

describe('list-workspaces tool', function () {
    it('returns the user workspaces', function () {
        $user = $this->actingAsUser();
        $workspace = $this->createUserWorkspace($user);

        OpnFormServer::actingAs($user)
            ->tool(ListWorkspacesTool::class)
            ->assertOk()
            ->assertSee($workspace->name);
    });

    it('returns empty list when user has no workspaces', function () {
        $user = $this->actingAsUser();

        OpnFormServer::actingAs($user)
            ->tool(ListWorkspacesTool::class)
            ->assertOk()
            ->assertSee('workspaces');
    });

    it('rejects unauthenticated access', function () {
        OpnFormServer::tool(ListWorkspacesTool::class)
            ->assertHasErrors();
    });
});

describe('list-forms tool', function () {
    it('returns forms in a workspace', function () {
        $user = $this->actingAsUser();
        $workspace = $this->createUserWorkspace($user);
        $form = $this->createForm($user, $workspace);

        OpnFormServer::actingAs($user)
            ->tool(ListFormsTool::class, [
                'workspace_id' => $workspace->id,
            ])
            ->assertOk()
            ->assertSee($form->title);
    });

    it('returns empty list when workspace has no forms', function () {
        $user = $this->actingAsUser();
        $workspace = $this->createUserWorkspace($user);

        OpnFormServer::actingAs($user)
            ->tool(ListFormsTool::class, [
                'workspace_id' => $workspace->id,
            ])
            ->assertOk()
            ->assertSee('forms');
    });

    it('requires workspace_id', function () {
        $user = $this->actingAsUser();

        OpnFormServer::actingAs($user)
            ->tool(ListFormsTool::class)
            ->assertHasErrors();
    });

    it('rejects access to workspace the user does not own', function () {
        $user = $this->actingAsUser();
        $otherUser = $this->createUser();
        $otherWorkspace = $this->createUserWorkspace($otherUser);

        OpnFormServer::actingAs($user)
            ->tool(ListFormsTool::class, [
                'workspace_id' => $otherWorkspace->id,
            ])
            ->assertHasErrors();
    });
});

describe('get-form tool', function () {
    it('returns full form details by ID', function () {
        $user = $this->actingAsUser();
        $workspace = $this->createUserWorkspace($user);
        $form = $this->createForm($user, $workspace);

        OpnFormServer::actingAs($user)
            ->tool(GetFormTool::class, [
                'form_id' => (string) $form->id,
            ])
            ->assertOk()
            ->assertSee($form->title)
            ->assertSee($form->slug);
    });

    it('returns full form details by slug', function () {
        $user = $this->actingAsUser();
        $workspace = $this->createUserWorkspace($user);
        $form = $this->createForm($user, $workspace);

        OpnFormServer::actingAs($user)
            ->tool(GetFormTool::class, [
                'form_id' => $form->slug,
            ])
            ->assertOk()
            ->assertSee($form->title);
    });

    it('rejects access to a form the user does not own', function () {
        $user = $this->actingAsUser();
        $otherUser = $this->createUser();
        $otherWorkspace = $this->createUserWorkspace($otherUser);
        $form = $this->createForm($otherUser, $otherWorkspace);

        OpnFormServer::actingAs($user)
            ->tool(GetFormTool::class, [
                'form_id' => (string) $form->id,
            ])
            ->assertHasErrors();
    });
});

describe('create-form tool', function () {
    it('creates a form with valid data', function () {
        $user = $this->actingAsUser();
        $workspace = $this->createUserWorkspace($user);

        OpnFormServer::actingAs($user)
            ->tool(CreateFormTool::class, [
                'workspace_id' => $workspace->id,
                'title' => 'MCP Test Form',
                'properties' => [
                    ['type' => 'text', 'name' => 'Full Name'],
                    ['type' => 'email', 'name' => 'Email Address'],
                ],
            ])
            ->assertOk()
            ->assertSee('MCP Test Form');

        $this->assertDatabaseHas('forms', [
            'title' => 'MCP Test Form',
            'workspace_id' => $workspace->id,
            'creator_id' => $user->id,
        ]);
    });

    it('defaults visibility to draft', function () {
        $user = $this->actingAsUser();
        $workspace = $this->createUserWorkspace($user);

        OpnFormServer::actingAs($user)
            ->tool(CreateFormTool::class, [
                'workspace_id' => $workspace->id,
                'title' => 'Draft Form',
                'properties' => [
                    ['type' => 'text', 'name' => 'Name'],
                ],
            ])
            ->assertOk()
            ->assertSee('draft');
    });

    it('rejects creating a form in another user workspace', function () {
        $user = $this->actingAsUser();
        $otherUser = $this->createUser();
        $otherWorkspace = $this->createUserWorkspace($otherUser);

        OpnFormServer::actingAs($user)
            ->tool(CreateFormTool::class, [
                'workspace_id' => $otherWorkspace->id,
                'title' => 'Unauthorized Form',
                'properties' => [
                    ['type' => 'text', 'name' => 'Name'],
                ],
            ])
            ->assertHasErrors();
    });

    it('requires title and properties', function () {
        $user = $this->actingAsUser();
        $workspace = $this->createUserWorkspace($user);

        OpnFormServer::actingAs($user)
            ->tool(CreateFormTool::class, [
                'workspace_id' => $workspace->id,
            ])
            ->assertHasErrors();
    });

    it('validates properties through FormPropertiesRule', function () {
        $user = $this->actingAsUser();
        $workspace = $this->createUserWorkspace($user);

        OpnFormServer::actingAs($user)
            ->tool(CreateFormTool::class, [
                'workspace_id' => $workspace->id,
                'title' => 'Bad Props Form',
                'properties' => [
                    ['name' => 'Missing Type'],
                ],
            ])
            ->assertHasErrors();
    });

    it('respects visibility param when creating a form', function () {
        $user = $this->actingAsUser();
        $workspace = $this->createUserWorkspace($user);

        OpnFormServer::actingAs($user)
            ->tool(CreateFormTool::class, [
                'workspace_id' => $workspace->id,
                'title' => 'Public Form',
                'visibility' => 'public',
                'properties' => [
                    ['type' => 'text', 'name' => 'Name'],
                ],
            ])
            ->assertOk()
            ->assertSee('public');

        $this->assertDatabaseHas('forms', [
            'title' => 'Public Form',
            'visibility' => 'public',
        ]);
    });
});

describe('update-form tool', function () {
    it('updates form title', function () {
        $user = $this->actingAsUser();
        $workspace = $this->createUserWorkspace($user);
        $form = $this->createForm($user, $workspace);

        OpnFormServer::actingAs($user)
            ->tool(UpdateFormTool::class, [
                'form_id' => (string) $form->id,
                'title' => 'Updated Title',
            ])
            ->assertOk()
            ->assertSee('Updated Title');

        $form->refresh();
        expect($form->title)->toBe('Updated Title');
    });

    it('updates form visibility', function () {
        $user = $this->actingAsUser();
        $workspace = $this->createUserWorkspace($user);
        $form = $this->createForm($user, $workspace);

        OpnFormServer::actingAs($user)
            ->tool(UpdateFormTool::class, [
                'form_id' => (string) $form->id,
                'visibility' => 'closed',
            ])
            ->assertOk();

        $form->refresh();
        expect($form->visibility)->toBe('closed');
    });

    it('rejects update with no valid fields', function () {
        $user = $this->actingAsUser();
        $workspace = $this->createUserWorkspace($user);
        $form = $this->createForm($user, $workspace);

        OpnFormServer::actingAs($user)
            ->tool(UpdateFormTool::class, [
                'form_id' => (string) $form->id,
            ])
            ->assertHasErrors();
    });

    it('backfills IDs on new properties during update', function () {
        $user = $this->actingAsUser();
        $workspace = $this->createUserWorkspace($user);
        $form = $this->createForm($user, $workspace);

        OpnFormServer::actingAs($user)
            ->tool(UpdateFormTool::class, [
                'form_id' => (string) $form->id,
                'properties' => [
                    ['type' => 'text', 'name' => 'New Field Without ID'],
                ],
            ])
            ->assertOk();

        $form->refresh();
        $firstProperty = $form->properties[0];
        expect($firstProperty['id'])->not()->toBeEmpty();
        expect($firstProperty['name'])->toBe('New Field Without ID');
    });

    it('validates properties through FormPropertiesRule on update', function () {
        $user = $this->actingAsUser();
        $workspace = $this->createUserWorkspace($user);
        $form = $this->createForm($user, $workspace);

        OpnFormServer::actingAs($user)
            ->tool(UpdateFormTool::class, [
                'form_id' => (string) $form->id,
                'properties' => [
                    ['name' => 'Missing Type Field'],
                ],
            ])
            ->assertHasErrors();
    });
});

describe('delete-form tool', function () {
    it('deletes a form', function () {
        $user = $this->actingAsUser();
        $workspace = $this->createUserWorkspace($user);
        $form = $this->createForm($user, $workspace);

        OpnFormServer::actingAs($user)
            ->tool(DeleteFormTool::class, [
                'form_id' => (string) $form->id,
            ])
            ->assertOk()
            ->assertSee('deleted');

        $this->assertSoftDeleted('forms', ['id' => $form->id]);
    });

    it('rejects deleting another user form', function () {
        $user = $this->actingAsUser();
        $otherUser = $this->createUser();
        $otherWorkspace = $this->createUserWorkspace($otherUser);
        $form = $this->createForm($otherUser, $otherWorkspace);

        OpnFormServer::actingAs($user)
            ->tool(DeleteFormTool::class, [
                'form_id' => (string) $form->id,
            ])
            ->assertHasErrors();
    });
});

describe('duplicate-form tool', function () {
    it('duplicates a form', function () {
        $user = $this->actingAsUser();
        $workspace = $this->createUserWorkspace($user);
        $form = $this->createForm($user, $workspace);
        $originalTitle = $form->title;

        OpnFormServer::actingAs($user)
            ->tool(DuplicateFormTool::class, [
                'form_id' => (string) $form->id,
            ])
            ->assertOk()
            ->assertSee('Copy of ' . $originalTitle);

        expect($workspace->forms()->count())->toBe(2);
    });
});

describe('create-form tool (draft mode)', function () {
    it('returns draft form data without authentication', function () {
        OpnFormServer::tool(CreateFormTool::class, [
            'title' => 'Guest Form',
            'properties' => [
                ['type' => 'text', 'name' => 'Name'],
                ['type' => 'email', 'name' => 'Email'],
            ],
        ])
            ->assertOk()
            ->assertSee('Guest Form')
            ->assertSee('register')
            ->assertSee('next_steps');
    });

    it('returns draft when workspace_id is omitted even if authenticated', function () {
        $user = $this->actingAsUser();

        OpnFormServer::actingAs($user)
            ->tool(CreateFormTool::class, [
                'title' => 'Draft While Logged In',
                'properties' => [
                    ['type' => 'text', 'name' => 'Name'],
                ],
            ])
            ->assertOk()
            ->assertSee('form_data')
            ->assertSee('next_steps');

        $this->assertDatabaseMissing('forms', ['title' => 'Draft While Logged In']);
    });

    it('does not persist anything to the database in draft mode', function () {
        OpnFormServer::tool(CreateFormTool::class, [
            'title' => 'Not Persisted',
            'properties' => [
                ['type' => 'text', 'name' => 'Temp'],
            ],
        ])
            ->assertOk();

        $this->assertDatabaseMissing('forms', ['title' => 'Not Persisted']);
    });
});

describe('field-types resource', function () {
    it('returns field type catalog', function () {
        OpnFormServer::resource(FieldTypesResource::class)
            ->assertOk()
            ->assertSee('input_fields')
            ->assertSee('text')
            ->assertSee('email')
            ->assertSee('select')
            ->assertSee('layout_blocks');
    });
});
