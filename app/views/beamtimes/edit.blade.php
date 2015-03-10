@extends('layouts.default')

@section('title')
Edit {{ $beamtime->name }}
@stop

@section('styles')
@parent
.form-control-feedback-large-h2 {
    position: absolute;
    top: 40px;
    display: block;
}
.from-control-feedback-textarea {
    position: absolute;
    top: 25px;
    right: 25px;
    display: block;
}
/* fix misalignment of form input and table in this view */
.form-group, .control-label {
  float: none;
}
@stop

@section('scripts')
<script type="text/javascript">
$(document).ready(function() {
    //$("[rel='tooltip']").tooltip();
    $("body").tooltip({ selector: '[data-toggle=tooltip]' });

});

$("[type='checkbox']").on("click", function() {
  var radios = $("[type='radio']");
  var checks = $("[type='checkbox']");

  var idx = checks.index($(this));  // index of the clicked checkbox element

  // toggle status button if maintenance is checked or not
  if (checks[idx].checked)
    $(".disabled:eq("+idx+")").hide();
  else
    $(".disabled:eq("+idx+")").show();

  if (radios[2*idx].disabled && !(radios[2*idx].checked || radios[2*idx+1].checked))
    radios[2*idx+1].checked = true;

  radios[2*idx].disabled = !radios[2*idx].disabled;
  radios[2*idx+1].disabled = !radios[2*idx+1].disabled;
/*  if (this.checked) {
    radios[2*idx].disabled = true;
    radios[2*idx+1].disabled = true;
  } else {
    radios[2*idx].disabled = false;
    radios[2*idx+1].disabled = false;
  }*/
});

$(document).ready(function() {
  var radios = $("[type='radio']");
  var checks = $("[type='checkbox']");

/*  checks.each(function() {
    if (this.checked) {
      radios[2*this.index()].disabled = true;
      radios[2*this.index()+1].disabled = true;
    }
  }*/
  for (var i = 0; i < checks.length; ++i) {
    if (checks[i].checked) {
      radios[2*i].disabled = true;
      radios[2*i+1].disabled = true;
      // hide status buttons if maintenance is checked
      $(".disabled:eq("+i+")").hide();
    }
  }
});

function toggleRadio(id)
{
  document.getElementById(id).disabled = !document.getElementById(id).disabled;
}
</script>
@stop

@section('content')
{{ Form::open(['route' => array('beamtimes.update', $beamtime->id), 'method' => 'PATCH']) }}
<div class="col-lg-10 col-lg-offset-1">
    <div class="page-header">
      <table width="100%">
        <tr>
          <td>
            <div class="form-group {{{ $errors->has('name') ? 'has-error has-feedback' : '' }}}" style="padding-left: 15px;">
              <h2>
                {{ Form::label('name', 'Beamtime: ', array('class' => 'control-label', 'style' => 'font-weight: normal; padding-left: 15px;')) }}
                {{ Form::text('name', $beamtime->name, array('class' => 'form-control input-lg', 'id' => 'inputError2 inputLarge', 'autofocus' => 'autofocus')) }}
                {{ $errors->has('name') ? '<span class="glyphicon glyphicon-remove form-control-feedback form-control-feedback-large-h2"></span>' : '' }}
              </h2>
              <h3>
                <p class="help-block">{{ $errors->first('name') }}</p>
              </h3>
            </div>
          </td>
          <td align="right">
            {{ Form::submit('Apply Changes', array('class' => 'btn btn-primary')) }}
            {{ link_to(URL::previous(), 'Cancel', ['class' => 'btn btn-default']) }}
          </td>
        </tr>
      </table>
    </div>
    <div class="col-lg-8 form-group {{{ $errors->has('description') ? 'has-error has-feedback' : '' }}}">
      {{ Form::label('description', 'Short beamtime description: ', array('class' => 'col-lg-8 control-label')) }}
      {{ Form::textarea('description', $beamtime->description, array('class' => 'form-control', 'rows' => '3', 'placeholder' => 'optional', 'id' => 'inputError2', 'autofocus' => 'autofocus')) }}
      {{ $errors->has('description') ? '<span class="glyphicon glyphicon-remove form-control-feedback from-control-feedback-textarea"></span>' : '' }}
      <p class="help-block">{{ $errors->first('description') }}</p>
    </div>

    {{-- Check if the beamtime contain shifts to avoid errors --}}
    @if (is_null($beamtime->shifts->first()))
    <h3 class="text-danger">Beamtime contains no shifts!</h3>
    @else
    @if (isset($beamtime))
    <div class="table-responsive">
    <table class="table table-striped table-hover">
      <thead>
        <tr>
          <th>#Shift</th>
          <th>Start</th>
          <th>Shift Workers</th>
          <th>#Shift Workers</th>
          <th>Remarks</th>
          <th>Maintenance</th>
          <th>Status</th>
          <th>Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php $i = 0; $day = ""; ?>
        @foreach ($shifts as $shift)
        @if ($day != date("l, d.m.Y", strtotime($shift->start)))
        <?php $day = date("l, d.m.Y", strtotime($shift->start)); ?>
        <tr class="active" style="padding-left:20px;">
          <th colspan=8>{{ $day }}</th>
        </tr>
        @endif
        <tr>
          <?php $td = ""; if ($n = $shift->users->count() > 0) $td = '<td rowspan="' . $n . '">'; else $td = '<td>'; ?>
          {{ $td }}{{ ++$i }}</td>
          {{ $td }}{{ $shift->start }}</td>
          {{-- check if users subscribed to this shift --}}
          @if ($shift->users->isEmpty())
          {{-- if not, then display this --}}
          <td>Nobody subscribed</td>
          @else
          {{-- otherwise show the subscribed users and display open shifts --}}
          <td><?php $shift->users->each(function($user)  // $shift->users returns a Collection of User objects which are connected to the current Shift object via the corresponding pivot table; with Collection::each we can iterate over this Collection instead of creating a foreach loop
          {
          	echo '<span rel="tooltip" data-toggle="tooltip" data-placement="top" title="Rating: ' . $user->rating . '">' . $user->first_name . ' ' . $user->last_name . '</span> (' . $user->workgroup->short . ')<br />';
          });
          ?></td>
          @endif
          {{-- {{ $td }}{{ Form::radio('n_crew[$i-1]', '1'@if ($shift->n_crew == 1) echo ", true" @endif) }}<br />{{ Form::radio('n_crew[$i-1]', '2'@if ($shift->n_crew == 2) echo ", true" @endif) }}</td> --}}
          {{ $td }}
            <div class="radio">
              <label>
                <?php echo Form::radio("n_crew[" . $shift->id . "]", '1', ($shift->n_crew == 1 ? true : false), array('id' => 'optionsRadios1')); ?>
                1
              </label>
              &nbsp;&nbsp;&nbsp;
              <label>
                <?php echo Form::radio("n_crew[" . $shift->id . "]", '2', ($shift->n_crew == 2 ? true : false), array('id' => 'optionsRadios2')); ?>
                2
              </label>
            </div>
          </td>
          {{ $td }}{{ Form::text('remarks[' . $shift->id . ']', $shift->remark, array('class' => 'form-control input-sm')) }}</td>
          {{-- //TODO: {{ Form::checkbox('maintenance[]', $shift->id, true) }} --}}
          {{ $td }}
            <div class="checkbox">
              <label>
                {{ Form::checkbox('maintenance[]', $shift->id, $shift->maintenance, array('onchange' => 'toggleRadio('.$shift->id.')')) }}
                {{-- don't use disable in the class attribute, otherwise the tooltip function won't work --}}
                <a rel="tooltip" data-toggle="tooltip" data-placement="top" data-original-title="Maintenance" class="btn btn-info btn-xs"><span class="fa fa-wrench"></span></a>
              </label>
            </div>
          </td>
          {{ $td }}@if ($shift->users->count() == 0) <a href="#" class="btn btn-danger btn-sm disabled">Empty</a>
          @elseif ($shift->users->sum('rating') < 5 ) <a href="#" class="btn btn-warning btn-sm disabled">Bad</a>
          @elseif ($shift->users->sum('rating') < 8 ) <a href="#" class="btn btn-primary btn-sm disabled">Okay</a>
          @else <a href="#" class="btn btn-success btn-sm disabled">Perfect</a>
          @endif</td>
          {{ $td }}</td>
        </tr>
        @endforeach
      </tbody>
    </table>
    </div>
    <div>
      <table border=0 width=95%>
        <tr>
          <td>Total {{ $shifts->filter(function($shift){ return !$shift->maintenance; })->count() }} shifts, {{{ $shifts->filter(function($shift){ return $shift->maintenance; })->count() }}} maintenance shifts, {{ $shifts->sum('n_crew') }} individual shifts</td>
          <td align="right">
            {{ Form::submit('Apply Changes', array('class' => 'btn btn-primary')) }}
          </td>
        </tr>
      </table>
    </div>
    @else
    <h3 class="text-danger">Beamtime not found!</h3>
    @endif
    @endif  {{-- end of check if beamtime contains shifts --}}
</div>
{{ Form::close() }}
@stop

