<?php

namespace App\Rules;

use App\Helpers\DateTimeHelper;
use App\Models\JobPosting;
use Carbon\Carbon;
use Illuminate\Contracts\Validation\Rule;

class CheckHoursRule implements Rule
{
    protected $rangeHoursType;
    protected $workTimeType;
    /**
     * Create a new rule instance.
     *
     * @return void
     */
    public function __construct($rangeHoursType, $workTimeType)
    {
        $this->rangeHoursType = $rangeHoursType;
        $this->workTimeType = $workTimeType;
    }

    /**
     * Determine if the validation rule passes.
     *
     * @param  string  $attribute
     * @param  mixed  $value
     * @return bool
     */
    public function passes($attribute, $value)
    {
        if ($this->rangeHoursType == JobPosting::HALF_DAY) {
            if ($this->workTimeType == JobPosting::TYPE_MORNING) {
                return !!preg_match('/^(1[0-1]|0?[0-9]):00|30/', $value);
            } else {
                return !!preg_match('/^(1[0-2]|0?[1-9]):00|30/', $value);
            }
        }

        return true;
    }

    /**
     * Get the validation error message.
     *
     * @return string
     */
    public function message()
    {
        return trans('validation.COM.999');
    }
}
