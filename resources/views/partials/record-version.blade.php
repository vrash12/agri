@if(isset($record) && $record instanceof \Illuminate\Database\Eloquent\Model && $record->exists)
  <input type="hidden" name="_record_version" value="{{ \App\Support\ConcurrentWrite::version($record) }}">
@endif
