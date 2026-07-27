@livewire(
    \App\Livewire\Admin\RecordActivityLog::class,
    [
        'subjectType' => $subjectType,
        'subjectId' => $subjectId,
        'relatedSubjects' => $relatedSubjects ?? [],
    ],
    key('activity-log-'.$subjectType.'-'.$subjectId)
)
