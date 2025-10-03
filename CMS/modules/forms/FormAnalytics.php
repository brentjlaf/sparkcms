<?php
// File: FormAnalytics.php
// Provides aggregated metrics for the forms dashboard.

require_once __DIR__ . '/FormRepository.php';

class FormAnalytics
{
    /** @var FormRepository */
    private $repository;

    /** @var int */
    private $referenceTime;

    /**
     * @param FormRepository $repository
     * @param int|null $referenceTime
     */
    public function __construct(FormRepository $repository, ?int $referenceTime = null)
    {
        $this->repository = $repository;
        $this->referenceTime = $referenceTime ?? time();
    }

    /**
     * Build the dashboard statistics context used by the view.
     *
     * @return array{
     *     totalForms:int,
     *     totalSubmissions:int,
     *     recentSubmissions:int,
     *     activeForms:int,
     *     lastSubmissionTimestamp:?int,
     *     lastSubmissionLabel:string
     * }
     */
    public function getDashboardContext(): array
    {
        $forms = $this->repository->getForms();
        $submissions = $this->repository->getSubmissions();

        $totalForms = count($forms);
        $totalSubmissions = count($submissions);

        $activeForms = [];
        $recentSubmissions = 0;
        $latestSubmission = 0;

        $threshold = $this->referenceTime - (30 * 24 * 60 * 60);

        foreach ($submissions as $submission) {
            if (!is_array($submission)) {
                continue;
            }

            if (isset($submission['form_id'])) {
                $activeForms[(int) $submission['form_id']] = true;
            }

            $timestamp = FormRepository::extractTimestamp($submission);
            if ($timestamp <= 0) {
                continue;
            }

            if ($timestamp > $latestSubmission) {
                $latestSubmission = $timestamp;
            }

            if ($timestamp >= $threshold) {
                $recentSubmissions++;
            }
        }

        $lastSubmissionLabel = $latestSubmission > 0
            ? date('M j, Y g:i A', $latestSubmission)
            : 'No submissions yet';

        return [
            'totalForms' => $totalForms,
            'totalSubmissions' => $totalSubmissions,
            'recentSubmissions' => $recentSubmissions,
            'activeForms' => count($activeForms),
            'lastSubmissionTimestamp' => $latestSubmission > 0 ? $latestSubmission : null,
            'lastSubmissionLabel' => $lastSubmissionLabel,
        ];
    }
}
