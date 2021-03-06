@extends('layouts.default')

@section('title')
{{ $beamtime->name }}
@stop

@section('scripts')
<script type="text/javascript">
$(document).ready(function() {
    //$("[rel='tooltip']").tooltip();
    $("body").tooltip({ selector: '[data-toggle=tooltip]' });

    // add a separate tooltip handler for the case of two data-toggle elements like modal + tooltip
    $('[data-tooltip="tooltip"]').tooltip();
});
</script>
@stop

@section('content')
<div class="col-lg-10 col-lg-offset-1">
    <div class="page-header">
      @if (!empty($current))
      <table style="background-color: initial;" width="100%">
        <tr>
          <td>
            <h2>Beamtime: {{{ $beamtime->name }}}</h2>
          </td>
          <td class="text-right hidden-print">
            {{ link_to(URL::previous(), 'Cancel', ['class' => 'btn btn-default']) }}
          </td>
        </tr>
      </table>
      @else
      <h2>Beamtime: {{{ $beamtime->name }}}</h2>
      @endif
    </div>

    {{-- Check if the beamtime contain shifts to avoid errors --}}
    @if (is_null($beamtime->shifts->first()))
    <h3 class="text-danger">Beamtime contains no shifts!</h3>
    @else
    @if (isset($beamtime))
    {{-- show a button to accept the swap request here if it is currently shown --}}
    @if (!empty($org) && !empty($req))
    <table style="background-color: initial;" width="100%">
      <tr>
        <td>
          <?php
          	$type = 'Swap Request';
          	$route = 'swaps.update';
          	$method = 'PUT';
          	if (Swap::whereHash($swap)->first()->is_request()) {
          		$type = 'Shift Request';
          		$route = 'swaps.shift_request';
          		$method = 'PATCH';
          	}
          ?>
          <h3 class="text-warning"><b>{{{ $type }}}</b></h3>
          <p>Do you accept this {{ strtolower($type) }} from {{{ User::find(Swap::whereHash($swap)->first()->user_id)->get_full_name() }}}?</p>
        </td>
        <td style="padding-left:20px; padding-top:15px;">
          {{ Form::open(['route' => array($route, $swap), 'method' => $method, 'class' => 'hidden-print', 'style' => 'float: left; margin-right: 5px;', 'role' => 'form']) }}
            {{ Form::submit('Accept', array('class' => 'btn btn-primary')) }}
          {{ Form::close() }}
          {{ Form::open(['route' => array($route, $swap), 'method' => $method, 'class' => 'hidden-print', 'role' => 'form']) }}
            {{ Form::hidden('action', 'decline') }}
            {{ Form::submit('Decline', array('class' => 'btn btn-danger')) }}
          {{ Form::close() }}
        </td>
      </tr>
    </table>
    @else
    <div class="hidden-print">
      <h3>Progress</h3>
      <?php
      	$now = new DateTime();
      	$start = $beamtime->start();
      	$end = $beamtime->end();
      ?>
      @if ($now < $start)
      <?php $diff = $now->diff($start); ?>
      <p class="text-primary">Beamtime will start in <?php  // show time difference until beamtime starts according to the time span
      	if ($diff->days > 0)
      		echo $diff->format('%a days and %h hours.');
      	elseif ($diff->days === 0 && $diff->h > 0)
      		echo $diff->format('%h hours and %i minutes.');
      	else
      		echo $diff->format('%i minutes.');
      ?></p>
      @elseif ($now > $end)
      <?php $diff = $now->diff($end); ?>
      <p class="text-success">Beamtime ended {{{ $diff->format('%a days ago') }}}.</p>
      @else
      <?php  // calculate progress of the current beamtime
      	$length = $end->getTimestamp() - $start->getTimestamp();
      	$elapsed = $now->getTimestamp() - $start->getTimestamp();
      	$progress = round($elapsed/$length*100, 2);
      ?>
      <div class="progress progress-striped">
        <div class="progress-bar progress-bar-success" style="width: {{{ $progress }}}%"></div>
      </div>
      @endif
    </div>
    @endif  {{-- swap request --}}
    <div class="table-responsive">
    <table class="table table-striped table-hover">
      <thead>
        <tr>
          <th>#Shift</th>
          <th>Start</th>
          <th>Duration</th>
          <th>Shift Workers</th>
          <th>Remarks</th>
          <th>Status</th>
          <th>Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php $day = ""; $other_user = 0; if (!empty($current)) $other_user = $shifts->find($current)->get_other_user_id(Auth::id());  // save the id of the other user on the original shift; 0 if not existent ?>
        @foreach ($shifts as $shift)
        @if ($day !== date("l, d.m.Y", strtotime($shift->start)))
        <?php $day = date("l, d.m.Y", strtotime($shift->start)); ?>
        <thead>
          <tr class="active" style="padding-left:20px;">
            <th colspan=7>{{ $day }}</th>
          </tr>
        </thead>
        @endif
        @if (!empty($current) && $current == $shift->id)
        <tr class="info">
        @elseif (!empty($org) && $org == $shift->id)
        <tr class="info">
        @elseif (!empty($req) && $req == $shift->id)
        <tr class="info">
        @else
        <tr>
        @endif
          <?php $td = ""; if ($n = $shift->users->count() > 0) $td = '<td rowspan="' . $n . '">'; else $td = '<td>'; ?>
          {{ $td }}</td>
          {{ $td }}{{ $shift->start }}</td>
          {{ $td }}{{ $shift->duration }} hours</td>
          {{-- check if users subscribed to this shift and it's not maintenance --}}
          @if ($shift->users->isEmpty() && !$shift->maintenance)
          {{-- if not, then display this --}}
          <td>Nobody subscribed yet</td>
          @else
          {{-- otherwise show the subscribed users and display open shifts --}}
          <td><?php $shift->users->each(function($user)  // $shift->users returns a Collection of User objects which are connected to the current Shift object via the corresponding pivot table; with Collection::each we can iterate over this Collection instead of creating a foreach loop
          {
          	echo '<span rel="tooltip" data-toggle="tooltip" data-placement="top" title="Rating: ' . $user->rating . '">' . $user->first_name . ' ' . $user->last_name . '</span> (' . $user->workgroup->name . ')<br />';
          });
          ?></td>
          @endif
          {{ $td }}{{ $shift->remark }}</td>
          {{ $td }}@if ($shift->maintenance) <a href="#" class="btn btn-info btn-sm disabled">Maintenance</a>
          @elseif ($shift->rating() == 0) <a href="#" class="btn btn-danger btn-sm disabled">Empty</a>
          @elseif ($shift->rating() < Shift::RATING_GOOD) <a href="#" class="btn btn-warning btn-sm disabled">Bad</a>
          @elseif ($shift->rating() < Shift::RATING_PERFECT) <a href="#" class="btn btn-primary btn-sm disabled">Good</a>
          @else <a href="#" class="btn btn-success btn-sm disabled">Perfect</a>
          @endif</td>
          {{-- only show swap buttons if shift is not empty (which is true for maintenance) and not in the future as well as the $now and $current variable is set which should be true in case of swap selection ($org and $req not set); additionally check if another user is subscribed to the original shift that this user is not the only one subscribed to this shift as well --}}
          {{ $td }}@if (!$shift->users->find(Auth::id()) && !$shift->users->isEmpty() && !empty($now) && $now < new DateTime($shift->start) && !empty($current) && ( !$shift->users->find($other_user) || $shift->users->count() > 1 ))
          <?php
          	$text = '<p>Please choose the users who should receive your swap request:</p>';
          	if ($shift->users->count() > 1) {
          		foreach ($shift->users as $user) {
          			$text .= "\n<div class='checkbox'>";
          			$text .= "\n  <label>";
          			$text .= "\n    <input type='checkbox' name='user[]' value='" . $user->id . "'>";
          			$text .= "\n    " . $user->first_name;
          			$text .= "\n  </label>";
          			$text .= "\n</div>";
          		}
          	}
          ?>
          {{ Form::open(['route' => array('swaps.store', $current, $shift->id), 'class' => 'hidden-print', 'style' => 'float: left;', 'role' => 'form']) }}
              {{-- only show selection dialogue if both users are available for swapping (none of the users are subscribed to the original shift) --}}
              @if ($shift->users->count() > 1 && !$shift->users->find($other_user))
              <button type="button" class="btn btn-default btn-sm" data-toggle="modal" data-target=".request-modal-{{{$shift->id}}}" data-tooltip="tooltip" data-placement="top" title="Swap with this shift"><i class="fa fa-exchange fa-lg"></i></button>
              <?php $request = new ShiftRequest(); $request->modal('request-modal-'.$shift->id, $text); ?>
              @else
              <button type="submit" class="btn btn-default btn-sm" data-toggle="tooltip" data-placement="top" title="Swap with this shift"><i class="fa fa-exchange fa-lg"></i></button>
              @endif
          {{ Form::close() }}
          @endif</td>
        </tr>
        @endforeach
      </tbody>
    </table>
    </div>
    <div>
      Total {{ $shifts->filter(function($shift){ return !$shift->maintenance; })->count() }} shifts, {{{ $shifts->filter(function($shift){ return $shift->maintenance; })->count() }}} maintenance shifts, {{ $shifts->sum('n_crew') }} individual shifts
    </div>
    @else
    <h3 class="text-danger">Beamtime not found!</h3>
    @endif
    @endif  {{-- end of check if beamtime contains shifts --}}
</div>
@stop

