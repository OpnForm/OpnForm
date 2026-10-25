<?php

namespace App\Mcp\Support;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\ObjectType;

final class McpOutputSchema
{
    public static function draft(JsonSchema $schema): ObjectType
    {
        return $schema->object([
            'version' => $schema->integer()->min(1)->required(),
            'schema_version' => $schema->integer()->min(1)->required(),
            'status' => $schema->string()->required(),
            'expires_at' => $schema->string()->format('date-time')->required(),
            'definition' => $schema->object()->required(),
        ])->withoutAdditionalProperties();
    }

    public static function workspace(JsonSchema $schema): ObjectType
    {
        return $schema->object([
            'id' => $schema->integer()->min(1)->required(),
            'name' => $schema->string()->required(),
            'icon' => $schema->string()->nullable()->required(),
            'role' => $schema->string()->nullable()->required(),
            'can_write_forms' => $schema->boolean()->required(),
            'plan_tier' => $schema->string()->nullable()->required(),
        ])->withoutAdditionalProperties();
    }

    public static function pagination(JsonSchema $schema): ObjectType
    {
        return $schema->object([
            'page' => $schema->integer()->min(1)->required(),
            'per_page' => $schema->integer()->min(1)->required(),
            'total' => $schema->integer()->min(0)->required(),
            'last_page' => $schema->integer()->min(1)->required(),
        ])->withoutAdditionalProperties();
    }

    public static function formSummary(JsonSchema $schema): ObjectType
    {
        return $schema->object([
            'id' => $schema->integer()->min(1)->required(),
            'workspace_id' => $schema->integer()->min(1)->required(),
            'title' => $schema->string()->required(),
            'visibility' => $schema->string()->required(),
            'share_url' => $schema->string()->format('uri')->required(),
            'submissions_count' => $schema->integer()->min(0)->required(),
            'views_count' => $schema->integer()->min(0)->required(),
            'created_at' => $schema->string()->format('date-time')->nullable()->required(),
            'updated_at' => $schema->string()->format('date-time')->nullable()->required(),
        ])->withoutAdditionalProperties();
    }

    public static function form(JsonSchema $schema): ObjectType
    {
        return $schema->object([
            'id' => $schema->integer()->min(1)->required(),
            'workspace_id' => $schema->integer()->min(1)->required(),
            'title' => $schema->string()->required(),
            'visibility' => $schema->string()->required(),
            'share_url' => $schema->string()->format('uri')->required(),
            'submissions_count' => $schema->integer()->min(0)->required(),
            'views_count' => $schema->integer()->min(0)->required(),
            'created_at' => $schema->string()->format('date-time')->nullable()->required(),
            'updated_at' => $schema->string()->format('date-time')->nullable()->required(),
            'definition' => $schema->object()->required(),
            'revision' => $schema->string()->min(64)->max(64)->required(),
            'edit_url' => $schema->string()->format('uri')->required(),
        ])->withoutAdditionalProperties();
    }

    public static function submission(JsonSchema $schema): ObjectType
    {
        return $schema->object([
            'id' => $schema->integer()->min(1)->required(),
            'form_id' => $schema->integer()->min(1)->required(),
            'status' => $schema->string()->required(),
            'created_at' => $schema->string()->format('date-time')->nullable()->required(),
            'updated_at' => $schema->string()->format('date-time')->nullable()->required(),
            'completion_time_seconds' => $schema->number()->min(0)->nullable()->required(),
            'responses' => $schema->union(['object', 'array'])->required(),
            'ip_address' => $schema->string(),
        ])->withoutAdditionalProperties();
    }

    public static function fieldSummary(JsonSchema $schema): ObjectType
    {
        return $schema->object([
            'total_submissions' => $schema->integer()->min(0)->required(),
            'processed_submissions' => $schema->integer()->min(0)->required(),
            'is_limited' => $schema->boolean()->required(),
            'fields' => $schema->array()->items($schema->object([
                'id' => $schema->string()->required(),
                'name' => $schema->string()->required(),
                'type' => $schema->string()->required(),
                'answered_count' => $schema->integer()->min(0)->required(),
                'total_submissions' => $schema->integer()->min(0)->required(),
                'summary_type' => $schema->string()->required(),
                'data' => $schema->union(['object', 'array'])->required(),
            ])->withoutAdditionalProperties())->required(),
        ])->withoutAdditionalProperties();
    }
}
