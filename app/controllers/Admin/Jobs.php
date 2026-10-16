<?php
namespace App\Controllers\Admin;

use Pika\AdminController,
    Pika\JobRunner,
    Symfony\Component\HttpFoundation\Request,
    Symfony\Component\HttpFoundation\Response;

/** Read-only list of the jobs in app/jobs with the result of their last run. */
class Jobs extends AdminController
{
    public function index(Request $request, Response $response)
    {
        $jobs = array_map(function ($j) {
            $l = $j['last'];
            return [
                'name'     => $j['name'],
                'status'   => $l['status'] ?? 'never run',
                'tone'     => ['ok' => 'badge-success', 'failed' => 'badge-danger'][$l['status'] ?? ''] ?? '',
                'when'     => $l ? date('Y-m-d H:i:s', (int) $l['at']) : '',
                'seconds'  => $l['seconds'] ?? '',
                'failures' => $l['failures'] ?? 0,
                'message'  => $l['message'] ?? '',
            ];
        }, JobRunner::forApp()->all());

        return $response->setContent($this->view('jobs', ['title' => 'Jobs', 'nav' => 'jobs', 'jobs' => $jobs]));
    }
}
