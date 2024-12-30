<?php

namespace DTApi\Http\Controllers;

use DTApi\Models\Job;
use DTApi\Http\Requests;
use DTApi\Models\Distance;
use Illuminate\Http\Request;
use DTApi\Repository\BookingRepository;

/**
 * Class BookingController
 * @package DTApi\Http\Controllers
 */
class BookingController extends Controller
{

    /**
     * BookingController constructor.
     * @param BookingRepository $bookingRepository
     */
    public function __construct(public BookingRepository $bookingRepository)
    {
    }

    /**
     * @param FromRequest $request
     * @return Response
     */
    public function index(FromRequest $request): Response
    {
        $user_id = $request->get('user_id');
        $user = $request->__authenticatedUser;

        $response = [
            'error' => 'Unauthorized access or invalid request',
        ];
        if (!empty($user_id)) {
            $response = $this->bookingRepository->getUsersJobs($user);
        }else if (in_array($user->user_type, [config('app.admin_role_id'), config('app.superadmin_role_id')])) {
            $response = $this->bookingRepository->getAll($request);
        }

        return response()->json($response);
    }

    /**
     * @return Response
     */
    public function show(Booking $booking): Response
    {
        $job = $booking->load('translatorJobRel.user');

        return response($job);
    }

    /**
     * @param FromRequest $request
     * @return Response
     */
    public function store(FromRequest $request): Response
    {
        $data = $request->all();

        $response = $this->bookingRepository->store($request->__authenticatedUser, $data);

        return response($response);

    }

    /**
     * @param $id
     * @param FromRequest $request
     * @return Response
     */
    public function update(Job $job, FromRequest $request): Response
    {
        $data = $request->except(['_token', 'submit']);
        $cuser = $request->__authenticatedUser;
        $response = $this->bookingRepository->updateJob($job, $data, $cuser);

        return response($response);
    }

    /**
     * @param FromRequest $request
     * @return Response
     */
    public function immediateJobEmail(FromRequest $request): Response
    {
        $data = $request->all();

        $response = $this->bookingRepository->storeJobEmail($data);

        return response($response);
    }

    /**
     * @param FromRequest $request
     * @return Response | null
     */
    public function getHistory(FromRequest $request): Response | null
    {
        if($user_id = $request->get('user_id')) {

            $response = $this->bookingRepository->getUsersJobsHistory($user_id, $request);
            return response($response);
        }

        return null;
    }

    /**
     * @param FromRequest $request
     * @return Response
     */
    public function acceptJob(FromRequest $request): Response
    {
        $data = $request->all();
        $user = $request->__authenticatedUser;

        $response = $this->bookingRepository->acceptJob($data, $user);

        return response($response);
    }

    /**
     * @param FromRequest $request
     * @return Response
     */
    public function acceptJobWithId(FromRequest $request): Response
    {
        $data = $request->get('job_id');
        $user = $request->__authenticatedUser;

        $response = $this->bookingRepository->acceptJobWithId($data, $user);

        return response($response);
    }

    /**
     * @param FromRequest $request
     * @return Response
     */
    public function cancelJob(FromRequest $request): Response
    {
        $data = $request->all();
        $user = $request->__authenticatedUser;

        $response = $this->bookingRepository->cancelJobAjax($data, $user);

        return response($response);
    }

    /**
     * @param FromRequest $request
     * @return Response
     */
    public function endJob(FromRequest $request): Response
    {
        $data = $request->all();

        $response = $this->bookingRepository->endJob($data);

        return response($response);

    }

    /**
     * @param FromRequest $request
     * @return Response
     */
    public function customerNotCall(FromRequest $request): Response
    {
        $data = $request->all();

        $response = $this->bookingRepository->customerNotCall($data);

        return response($response);

    }

    /**
     * @param FromRequest $request
     * @return Response
     */
    public function getPotentialJobs(FromRequest $request): Response
    {
        $data = $request->all();
        $user = $request->__authenticatedUser;

        $response = $this->bookingRepository->getPotentialJobs($user);

        return response($response);
    }

    /**
     * @param FromRequest $request
     * @return Response
     */
    public function distanceFeed(FromRequest $request): Response
    {
        $data = $request->all();

        $distance = isset($data['distance']) && $data['distance'] != "" ? $data['distance'] : "";
        $time = isset($data['time']) && $data['time'] != "" ? $data['time'] : "";
        $jobid = isset($data['jobid']) && $data['jobid'] != "" ? $data['jobid'] : "";
        $session = isset($data['session_time']) && $data['session_time'] != "" ? $data['session_time'] : "";
        $manually_handled = isset($data['manually_handled']) && $data['manually_handled'] != "" ? $data['manually_handled'] : "";
        $by_admin = isset($data['by_admin']) && $data['by_admin'] != "" ? "yes" : "no";
        $admincomment = isset($data['admincomment']) && $data['admincomment'] != "" ? $data['admincomment'] : "";

        if ($data['flagged'] == 'true') {
            if($data['admincomment'] == '') return "Please, add comment";
            $flagged = 'yes';
        } else {
            $flagged = 'no';
        }
        if ($time || $distance) {

            Distance::where('job_id', '=', $jobid)->update(array('distance' => $distance, 'time' => $time));
        }

        if ($admincomment || $session || $flagged || $manually_handled || $by_admin) {

            Job::where('id', '=', $jobid)->update(array('admin_comments' => $admincomment, 'flagged' => $flagged, 'session_time' => $session, 'manually_handled' => $manually_handled, 'by_admin' => $by_admin));

        }

        return response('Record updated!');
    }

    /**
     * @param FromRequest $request
     * @return Response
     */
    public function reopen(FromRequest $request): Response
    {
        $data = $request->all();
        $response = $this->bookingRepository->reopen($data);

        return response($response);
    }

    /**
     * @param FromRequest $request
     * @return Response
     */
    public function resendNotifications(FromRequest $request): Response
    {
        $data = $request->all();
        $job = $this->bookingRepository->find($data['jobid']);
        $job_data = $this->bookingRepository->jobToData($job);
        $this->bookingRepository->sendNotificationTranslator($job, $job_data, '*');

        return response(['success' => 'Push sent']);
    }

    /**
     * Sends SMS to Translator
     * @param FromRequest $request
     * @return \Illuminate\Contracts\Routing\ResponseFactory|\Symfony\Component\HttpFoundation\Response
     */
    public function resendSMSNotifications(FromRequest $request)
    {
        $data = $request->all();
        $job = $this->bookingRepository->find($data['jobid']);
        $this->bookingRepository->jobToData($job);

        try {
            $this->bookingRepository->sendSMSNotificationToTranslator($job);
            return response(['success' => 'SMS sent']);
        } catch (\Exception $e) {
            return response(['success' => $e->getMessage()]);
        }
    }

}
