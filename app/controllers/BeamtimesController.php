<?php

class BeamtimesController extends \BaseController {

	protected $beamtime;

	public function __construct(Beamtime $beamtime)
	{
		$this->beamtime = $beamtime;
	}

	/**
	 * Display a listing of the resource.
	 *
	 * @return Response
	 */
	public function index()
	{
		// sort beamtimes in decreasing order by id to show the last created beamtime at the top
		//$beamtimes = $this->beamtime->orderBy('id', 'desc')->paginate(20);
		$beamtimes = Beamtime::all();

		// Add the start of the beamtime to every entry of the Collection of Beamtimes
		foreach ($beamtimes as $beamtime)
			$beamtime->start = $beamtime->start_string();
		// Sort the beamtimes by decreasing order
		$beamtimes->sortByDesc('start');

		// prepare pagination manually because it works on queries, not on collections
		$perPage = 20;
		$page = 1;
		if (Input::has('page'))
			$page = Input::get('page');
		$offset = ($page - 1) * $perPage;
		$beamtimes = Paginator::make($beamtimes->slice($offset, $perPage, true)->all(), $beamtimes->count(), $perPage);

		return View::make('beamtimes.index', ['beamtimes' => $beamtimes]);
	}


	/**
	 * Show the form for creating a new resource.
	 *
	 * @return Response
	 */
	public function create()
	{
		$hours = array();
		for ($i = 0; $i < 24; $i++)
			$hours[$i] = $i.':00';

		return View::make('beamtimes.create', ['hours' => $hours]);
	}


	/**
	 * Store a newly created resource in storage.
	 *
	 * @return Response
	 */
	public function store()
	{
		// only admins are allowed to create and remove beamtimes
		if (!Auth::user()->isAdmin())
			return Redirect::to('beamtimes');

		// check if entered data is correct
		$now = date("Y-m-d H:i:s");
		$rules = [
			'name' => 'required|max:100',
			'description' => 'max:500',
			'start' => 'required|date|after:'.$now,
			'end' => 'required|date|after:'.((Input::has('start')) ? Input::get('start') : $now),
			'duration' => 'required|integer|max:10'
		];

		$validation = Validator::make(Input::all(), $rules);

		if ($validation->fails())
			return Redirect::back()->withInput()->withErrors($validation->messages());

		// if all data is correct, continue with creating the beamtime
		$start = date(Input::get('start')."T".Input::get('sTime').":00:00");
		$end = date(Input::get('end')."T".Input::get('eTime').":00:00");
		//return 'Create '.Input::get('name').' - start is '.$start.' and end is '.$end.' - shift length '.Input::get('duration');
		$duration = Input::get('duration');
		$start = new DateTime($start);
		$begin = clone($start);
		$end = new DateTime($end);
		// length information of the beamtime
		$interval = $start->diff($end);
		// store beamtime information
		$this->beamtime->fill(Input::only('name', 'description'));
		$this->beamtime->save();
		/* create the shifts */
		$shifts = $this->beamtime->createShifts($start, $end, $duration);
		// create and save all normal shifts
		foreach ($shifts['normal'] as $shift) {
			$s = new Shift;
			$s->fill(array_add($shift, 'beamtime_id', $this->beamtime->id));
			$s->save();
		}
		// create and save the run coordinator shifts
		foreach ($shifts['rc'] as $shift) {
			$s = new RCShift;
			$s->fill(array_add($shift, 'beamtime_id', $this->beamtime->id));
			$s->save();
		}

		return Redirect::route('beamtimes.show', ['id' => $this->beamtime->id])
			->with('success', 'Beamtime created successfully! It starts at ' . $begin->format('Y-m-d H:i') . ' and ends at ' . $end->format('Y-m-d H:i') . '. Total length is ' . $interval->format('%a days and %h hours') . ', ' . count($shifts) . ' shifts created.');
	}


	/**
	 * Display the specified resource.
	 *
	 * @param  int  $id
	 * @return Response
	 */
	public function show($id)
	{
		if ($beamtime = Beamtime::find($id)) {
			$shifts = $beamtime->shifts;
			$rc_shifts = $beamtime->rcshifts;
		} else
			return 'Beamtime not found!';

		return View::make('beamtimes.show')->with('beamtime', $beamtime)->with('shifts', $shifts)->with('rc_shifts', $rc_shifts);
	}


	/**
	 * Show the form for editing the specified resource.
	 *
	 * @param  int  $id
	 * @return Response
	 */
	public function edit($id)
	{
		// only admins and run coordinators are allowed to edit beamtimes
		if (!Auth::user()->isAdmin())
			return Redirect::to('beamtimes/' . $id);

		if ($beamtime = Beamtime::find($id))
			$shifts = $beamtime->shifts;
		else
			return 'Beamtime not found!';

		return View::make('beamtimes.edit')->with('beamtime', $beamtime)->with('shifts', $shifts);
	}


	/**
	 * Update the specified resource in storage.
	 *
	 * @param  int  $id
	 * @return Response
	 */
	public function update($id)
	{
		// only admins are allowed to edit beamtimes
		if (!Auth::user()->isAdmin())
			return Redirect::to('beamtimes/' . $id);

		if ($beamtime = $this->beamtime->find($id))
			$shifts = $beamtime->shifts;
		else
			return 'Beamtime not found!';


		// check if the beamtime properties are correct
		if (!$beamtime->fill(Input::only('name', 'description'))->isValid())
			return Redirect::back()->withInput()->withErrors($beamtime->errors);

		// save the (new) name and description for this beamtime
		$beamtime->save();

		$n = Input::get('n_crew');
		$remarks = Input::get('remarks');
		// loop over all shifts in this beamtime; the array keys for the n_crew and remarks array correspond to the shift's id
		foreach ($shifts as $shift) {
			$id = $shift->id;
			$shift->remark = $remarks[$id];

			/* check for maintenance now */
			if (Input::get('maintenance')) {  // prevents an error if no maintenance shifts are set
				if (is_int(array_search($id, Input::get('maintenance')))) {  // array_search() returns the index of the value if it was found, so check if the returned value is an int which is true in case of maintenance
					// if there is maintenance during this shift, set the shift workers to zero
					$shift->n_crew = 0;
					$shift->maintenance = true;
					// if users are subscribed to this shift, send them an email and remove them
					if (!$shift->users->isEmpty()) {
						// mail content
						$subject = 'Automatic Cancellation of Shift Subscription';
						$msg = "Hello [USER],\r\n\r\n";
						$msg.= 'you\'ve subscribed to the shift on '. date("l, jS F Y, \s\\t\a\\r\\t\i\\n\g \a\\t H:i", strtotime($shift->start)) . ". This shift has been changed to a maintenance shift and you were automatically unsubscribed from this shift.\r\n\r\n";
						$msg.= 'You can use the following link to view the corresponding beamtime \'' . $beamtime->name . '\': ' . url() . '/beamtimes/' . $beamtime->id . "\r\n\r\n";
						$msg.= "In case you don't know why and haven't got any further information yet, you might want to contact one of the following run coordinators:\r\n\r\n";
						$beamtime->run_coordinators()->each(function($user) use(&$msg)
						{
							$msg.= $user->get_full_name() . ': ' . $user->email . "\r\n";
						});
						$msg.= "\r\nA2 Beamtime Scheduler";
						$success = true;

						// send the mail to every user from the shift
						$shift->users->each(function($user) use(&$success, $subject, $msg)
						{
							$success &= $user->mail($subject, str_replace(array('[USER]'), array($user->first_name), $msg));
						});

						$shift->users()->detach();
					}
				} else {
					// else set the given number of shift workers for this shift
					if (!array_key_exists($id, $n))  // in case no radio button was selected, prevent an error and set the number of shift workers to 2
						$shift->n_crew = 2;
					else  // otherwise assign the selected value
						$shift->n_crew = $n[$id];
					$shift->maintenance = false;
				}
			} else  // If there are no maintenance shifts, then it could be the case that maintenance shifts were removed. So check if there are still maintenance shifts and set them to normal shifts.
				if ($shift->maintenance) {
					// set the given number of shift workers for this shift
					if (!array_key_exists($id, $n))  // in case no radio button was selected, prevent an error and set the number of shift workers to 2
						$shift->n_crew = 2;
					else  // otherwise assign the selected value
						$shift->n_crew = $n[$id];
					$shift->maintenance = false;
				}

			//$shift->fill(['n_crew' => current(each($n)), 'remarks' => current(each($remarks))]);
			$shift->save();
		}

		return Redirect::back()->with('beamtime', $beamtime)->with('shifts', $shifts)->with('success', 'Beamtime edited successfully');
	}


	/**
	 * Remove the specified resource from storage.
	 *
	 * @param  int  $id
	 * @return Response
	 */
	public function destroy($id)
	{
		// only admins are allowed to create and remove beamtimes
		if (!Auth::user()->isAdmin())
			return Redirect::to('beamtimes');

		$beamtime = Beamtime::find($id);
		$beamtime->delete();

		// redirect to the beamtimes overview after deletion
		return Redirect::to('beamtimes')->with('success', 'Beamtime deleted successfully');
	}


	/**
	 * Show statistical information about the beamtimes
	 *
	 * @return Respone
	 */
	public function statistics($year = NULL)
	{
		// only admins and PIs can see statistics
		if (!Auth::user()->isAdmin() && !Auth::user()->isPI())
			return Redirect::to('beamtimes');

		if (!$year)
			$year = Input::get('year', NULL);
		return View::make('beamtimes.statistics')->with('year', $year);
	}


	/**
	 * Return an iCalendar file including all shifts the logged in user has taken in the specific beamtime.
	 *
	 * @param  int  $id
	 * @return ics file
	 */
	public function ics($id)
	{
		if (Auth::guest())
			return Redirect::guest('login');

		$user = Auth::user();
		$shifts = $user->shifts->filter(function($shift) use($id)
		{
			return $shift->beamtime_id == $id;
		});

		if (!$shifts->count())
			return Redirect::back()->with('error', "You haven't taken any shifts in this beamtime");

		date_default_timezone_set('Europe/Berlin');

		$vCalendar = new \Eluceo\iCal\Component\Calendar("Shifts of " . $user->get_full_name());

		$shifts->each(function($shift) use(&$vCalendar)
		{
			$vEvent = new \Eluceo\iCal\Component\Event();
			$vEvent->setDtStart(new DateTime($shift->start));
			$vEvent->setDtEnd($shift->end());
			$vEvent->setUseTimezone(true);
			$vEvent->setSummary('Shift');
			$vEvent->setDescription("Shift in beamtime \"" . $shift->beamtime->name . "\"");
			$vEvent->setDescriptionHTML('<b>Shift</b> in beamtime "<a href="' . url() . "/beamtimes/$shift->beamtime_id" . '">' . $shift->beamtime->name . '</a>"');
			$vEvent->setLocation("Institut für Kernphysik \nMainz \nGermany", 'A2 Counting Room', '49.991, 8.237');

			$vCalendar->addComponent($vEvent);
		});

		header('Content-Type: text/calendar; charset=utf-8');
		header('Content-Disposition: attachment; filename="cal.ics"');

		return $vCalendar->render();
	}


	/**
	 * Display the run coordinator shifts for the specified beamtime.
	 *
	 * @param  int  $id
	 * @return Response
	 */
	public function rc_show($id)
	{
		// restrict this view to run coordinators
		if (!Auth::user()->isRunCoordinator())
			return Redirect::to('beamtimes/' . $id);

		if ($beamtime = Beamtime::find($id))
			$rc_shifts = $beamtime->rcshifts;
		else
			return 'Beamtime not found!';

		return View::make('beamtimes.rc')->with('beamtime', $beamtime)->with('rc_shifts', $rc_shifts);
	}


	/**
	 * Update the run coordinator shifts
	 *
	 * @param  int  $id
	 * @return Response
	 */
	public function rc_update($id)
	{
		// restrict this view to run coordinators
		if (!Auth::user()->isRunCoordinator())
			return Redirect::to('beamtimes/' . $id);

		if ($beamtime = Beamtime::find($id))
			$rc_shifts = $beamtime->rcshifts;
		else
			return 'Beamtime not found!';

		// loop over all run coordinator shifts in this beamtime
		foreach ($rc_shifts as $shift) {
			$id = $shift->id;

			/* check for subscription status */
			if (Input::get('subscription')) {  // prevents an error if the user subscribed to no shifts
				if (is_int(array_search($id, Input::get('subscription')))) {  // array_search() returns the index of the value if it was found, so check if the returned value is an int which is true in case the user subscribed to this shift
					// if other users are subscribed to this shift, return an error
					if (!$shift->user->isEmpty() && $shift->user->first()->id != Auth::id())
						return Redirect::back()->withInput()->with('error', 'You chose a shift were a user is already subscribed to');
					// if the shift is empty, subscribe the user to this shift
					$shift->user()->attach(Auth::id());
				} else {
					// else the user might have unsibscribed, so check if there is a subscription from this user and remove the user if this is the case
					$shift->user()->detach(Auth::id());
				}
			} else  // If there are no subscriptions to shifts, then it could be the case that the user unsubscribed from all. So check if there are still subscriptions for this user and remove them
				if (!$shift->user->isEmpty() && $shift->user->first()->id == Auth::id()) {
					$shift->user()->detach(Auth::id());
				}

			$shift->save();
		}

		return Redirect::back()->with('beamtime', $beamtime)->with('rc_shifts', $rc_shifts)->with('success', 'Run coordinator shifts taken successfully');
	}

}
