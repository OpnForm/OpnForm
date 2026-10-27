<?php

use App\Jobs\Form\StoreFormSubmissionJob;
use App\Models\Forms\FormSubmission;

beforeEach(function () {
    $user = $this->actingAsBusinessUser();
    $workspace = $this->createUserWorkspace($user);
    $this->form = $this->createForm($user, $workspace, ['properties' => [
        ['id' => 'email', 'name' => 'Email', 'type' => 'email'],
        ['id' => 'code', 'name' => 'Code', 'type' => 'number'],
    ], 'database_fields_update' => ['email', 'code']]);
    $this->submission = new FormSubmission(['data' => ['email' => 'victim@example.test', 'code' => 0], 'status' => 'completed']);
    $this->submission->form_id = $this->form->id;
    $this->submission->save();
});

it('does not select a submission with incomplete matching criteria', function (array $data) {
    $job = new StoreFormSubmissionJob($this->form, $data);
    $method = new ReflectionMethod($job, 'resolveRecordToUpdate');
    expect($method->invoke($job, $data))->toBeNull();
})->with([
    [[]], [['email' => 'victim@example.test']], [['email' => 'victim@example.test', 'code' => null]],
    [['email' => '', 'code' => 0]], [['email' => [], 'code' => 0]],
]);

it('matches valid zero values without dropping a configured predicate', function () {
    $data = ['email' => 'victim@example.test', 'code' => 0];
    $job = new StoreFormSubmissionJob($this->form, $data);
    expect((new ReflectionMethod($job, 'resolveRecordToUpdate'))->invoke($job, $data))->toBe($this->submission->id);
});

it('does not match when a configured property no longer exists', function () {
    $this->form->database_fields_update = ['email', 'missing'];
    $job = new StoreFormSubmissionJob($this->form, ['email' => 'victim@example.test']);
    expect((new ReflectionMethod($job, 'resolveRecordToUpdate'))->invoke($job, $job->submissionData))->toBeNull();
});
