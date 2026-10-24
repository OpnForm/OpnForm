@component('mail::message', [
'noBranding' => $noBranding,
'emailAppearance' => $emailAppearance ?? [],
])

{!! $emailContent !!}

@if(($integrationData->link_edit_submission ?? false) && $form->editable_submissions)
@component('mail::button', ['url' => $form->share_url.'?submission_id='.$submission_id])
{{($form->editable_submissions_button_text ?? 'Edit submission')}}
@endcomponent
@endif

@if($integrationData->include_submission_data)
@foreach($fields as $field)
@if(isset($field['value']))
<div style="white-space: pre-wrap; border-top: 1px solid #9ca3af; padding-top: 12px; margin-top: 12px;">
    <b>{{$field['name']}}</b>
    @if(!empty($field['email_data']))
        @foreach($field['email_data'] as $file)
        <div style="margin-top: 8px;">
            @if(!empty($file['inline_cid']))
            <a href="{{$file['signed_url']}}" style="text-decoration: none;">
                <img
                    src="cid:{{$file['inline_cid']}}"
                    alt="{{$file['label']}}"
                    style="display: block; max-width: 100%; height: auto; border: 0; border-radius: 6px;"
                >
            </a>
            @endif
            <a href="{{$file['signed_url']}}" style="display: inline-block; margin-top: 6px; text-decoration: none;">
                {{$file['is_image'] ? '🖼️' : '📎'}} {{$file['label']}}
            </a>
        </div>
        @endforeach
    @elseif(!empty($field['value_is_html']))
    {!! $field['value'] !!}
    @else
    {{ is_array($field['value']) ? implode(',', $field['value']) : $field['value'] }}
    @endif
</div>
@endif
@endforeach
@endif

@endcomponent
