@component('mail::message', ['noBranding' => $noBranding])

{!! $emailContent !!}

@if(($integrationData->link_edit_submission ?? false) && $form->editable_submissions)
@component('mail::button', ['url' => $form->share_url.'?submission_id='.$submission_id])
{{($form->editable_submissions_button_text ?? 'Edit submission')}}
@endcomponent
@endif

@if($integrationData->include_submission_data)
@foreach($fields as $field)
@if(isset($field['value']))
<p style="white-space: pre-wrap; border-top: 1px solid #9ca3af;">
    <b>{{$field['name']}}</b>
    {!! is_array($field['value'])?implode(',',$field['value']):$field['value']!!}
</p>
@endif
@endforeach
@endif

@endcomponent