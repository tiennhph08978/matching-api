<?php

namespace App\Http\Resources\Recruiter\Job;

use App\Helpers\FileHelper;
use Illuminate\Http\Resources\Json\JsonResource;

class DetailImageResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array|\Illuminate\Contracts\Support\Arrayable|\JsonSerializable
     */
    public function toArray($request)
    {
        return [
            'url' => FileHelper::getFullUrl($this->url),
        ];
    }
}
