<?php

namespace App\Livewire;

use App\Models\Course;
use App\Support\Enrollments\JoinWaitlist;
use Illuminate\Contracts\View\View;
use Livewire\Component;

/**
 * The "Kurz připravujeme" card of a course without open registration: an
 * e-mail field that subscribes the visitor to the course's interest list —
 * they get the "course_registration_opened" e-mail once a series opens.
 */
class CourseInterestForm extends Component
{
    public string $courseId;

    public string $email = '';

    public bool $subscribed = false;

    public function mount(Course $course): void
    {
        $this->courseId = (string) $course->getKey();
    }

    public function subscribe(): void
    {
        $this->validate(
            ['email' => ['required', 'email', 'max:255']],
            [],
            ['email' => 'e-mail'],
        );

        JoinWaitlist::handle(
            Course::query()->findOrFail($this->courseId),
            null,
            trim($this->email),
        );

        $this->subscribed = true;
        $this->reset('email');
    }

    public function render(): View
    {
        return view('livewire.course-interest-form');
    }
}
