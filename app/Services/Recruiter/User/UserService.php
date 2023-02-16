<?php

namespace App\Services\Recruiter\User;

use App\Exceptions\InputException;
use App\Helpers\JobHelper;
use App\Helpers\UserHelper;
use App\Models\FavoriteUser;
use App\Models\Image;
use App\Models\Notification;
use App\Models\User;
use App\Services\Service;
use Carbon\Carbon;
use Exception;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Session;

class UserService extends Service
{
    const APP_MODE = 1;
    /**
     * @return array
     */
    public function getNewUsers()
    {
        $recruiter = $this->user;

        $favorites = FavoriteUser::query()
            ->where('user_id', $recruiter->id)
            ->pluck('favorite_user_id')
            ->toArray();

        $userNewList = User::query()->roleUser()
            ->whereNotIn('users.id', $favorites)
            ->where('created_at', '>=', DB::raw(sprintf(
                "DATE_SUB('%s', INTERVAL %s DAY)",
                Carbon::parse()->format('Y-m-d'),
                config('validate.date_range.new_user_marker')
            )))
            ->with([
                'avatarBanner',
                'province',
                'province.provinceDistrict',
                'desiredConditionUser',
                'desiredConditionUser.salaryType',
                'desiredConditionUser.province',
                'desiredConditionUser.province.provinceDistrict',
                'favoriteJobs'
            ])
            ->orderBy('created_at', 'desc')
            ->take(config('paginate.user.new_amount'))
            ->get();

        if ($userNewList) {
            return self::getUserInfoForListUser($recruiter, $userNewList);
        }
    }

    public function getAppNewUser($userId = null)
    {
        $recruiter = $this->user;
        $isReset = false;
        $user = null;
        $appModeData = $recruiter->app_mode_data;

        if (!isset($appModeData['new'])) {
            $appModeData['new'] = ['withoutIds' => []];
        }

        $withoutIds = $appModeData['new']['withoutIds'];

        $relation = [
            'avatarBanner',
            'avatarDetails',
            'province',
            'province.provinceDistrict',
            'desiredConditionUser',
            'desiredConditionUser.salaryType',
            'desiredConditionUser.province',
            'desiredConditionUser.province.provinceDistrict',
            'favoriteJobs',
            'userLicensesQualifications',
            'userLearningHistories',
        ];

        $whereDate = DB::raw(sprintf(
            "DATE_SUB('%s', INTERVAL %s DAY)",
            Carbon::parse()->format('Y-m-d'),
            config('validate.date_range.new_user_marker')
        ));

        $favorites = FavoriteUser::query()
            ->where('user_id', $recruiter->id)
            ->pluck('favorite_user_id')
            ->toArray();

        $users = User::query()->roleUser()
            ->whereNotIn('id', $favorites)
            ->where('created_at', '>=', $whereDate)
            ->orderBy('created_at', 'desc')
            ->with($relation)
            ->get();

        if ($userId) {
            $user = $users->filter(function($user) use ($withoutIds, $userId) {
                return $user->id != $userId && !in_array($user->id, $withoutIds);
            })->first();

            if (is_null($user)) {
                $withoutIds = $userId ? [$userId] : [];
                $isReset = true;
            }
        } elseif (count($withoutIds)) {
            $user = $users->filter(function($user) use ($withoutIds) {
               return $user->id == $withoutIds[count($withoutIds) - 1];
            })->first();
        }

        $user = $user ?: $users->first();

        $withoutIds = array_unique(array_merge($favorites, $withoutIds));

        if (count($withoutIds) && $userId) {
            $key = array_search($userId, $withoutIds);
            if ($key >= 0) {
                unset($withoutIds[$key]);
                $withoutIds[] = $userId;
            }
        }

        if ($user) {
            $appModeData['new']['withoutIds'] = array_unique(array_merge($isReset ? $favorites : $withoutIds, [(int)$user->id]));
            $recruiter->update(['app_mode_data' => $appModeData]);

            return  self::getUserInfoForListUser($this->user, [$user]);
        }
    }

    /**
     * @return array
     */
    public function getSuggestUsers()
    {
        $recruiter = $this->user;
        $jobOwnedIds = $recruiter->jobsOwned()->pluck('job_postings.id')->toArray();

        $favorites = FavoriteUser::query()
            ->where('user_id', $recruiter->id)
            ->pluck('favorite_user_id')
            ->toArray();

        $userSuggestList = User::query()->roleUser()
            ->select('users.*', 'user_id', DB::raw('sum(suitability_point) as point'))
            ->join('user_job_desired_matches', 'users.id', '=', 'user_id')
            ->whereIn('job_id', $jobOwnedIds)
            ->whereNotIn('users.id', $favorites)
            ->groupBy('user_id')
            ->orderBy('point', 'DESC')
            ->orderBy('last_login_at', 'DESC')
            ->orderBy('users.created_at', 'DESC')
            ->with('avatarBanner')
            ->take(config('paginate.user.suggest_amount'))
            ->get();

        if ($userSuggestList) {
            return self::getUserInfoForListUser($recruiter, $userSuggestList);
        }
    }

    /**
     * @return array
     */
    public function getAppSuggestUsers($userId = null)
    {
        $recruiter = $this->user;
        $isReset = false;
        $userSuggest = null;
        $appModeData = $recruiter->app_mode_data;

        if (!isset($appModeData['suggest'])) {
            $appModeData['suggest'] = ['withoutIds' => []];
        }

        $withoutIds = $appModeData['suggest']['withoutIds'];

        $favorites = FavoriteUser::query()
            ->where('user_id', $recruiter->id)
            ->pluck('favorite_user_id')
            ->toArray();

        $jobOwnedIds = $recruiter->jobsOwned()->pluck('job_postings.id')->toArray();

        $userSuggests = User::query()->roleUser()
            ->select('users.*', 'user_job_desired_matches.user_id', DB::raw('sum(suitability_point) as point'))
            ->join('user_job_desired_matches', 'users.id', '=', 'user_job_desired_matches.user_id')
            ->leftJoin('user_licenses_qualifications', 'users.id', '=', 'user_licenses_qualifications.user_id')
            ->leftJoin('user_learning_histories', 'users.id', '=', 'user_learning_histories.user_id')
            ->whereIn('job_id', $jobOwnedIds)
            ->whereNotIn('users.id', $favorites)
            ->groupBy('user_job_desired_matches.user_id')
            ->orderBy('point', 'DESC')
            ->orderBy('last_login_at', 'DESC')
            ->orderBy('users.created_at', 'DESC')
            ->with(['avatarBanner', 'avatarDetails'])
            ->get();

        if ($userId) {
            $userSuggest = $userSuggests->filter(function($user) use ($withoutIds, $userId) {
                return $user->id != $userId && !in_array($user->id, $withoutIds);
            })->first();

            if (is_null($userSuggest)) {
                $withoutIds = $userId ? [$userId] : [];
                $isReset = true;
            }
        } elseif (count($withoutIds)) {
            $userSuggest = $userSuggests->filter(function($user) use ($withoutIds) {
                return $user->id == $withoutIds[count($withoutIds) - 1];
            })->first();
        }

        $userSuggest = $userSuggest ?: $userSuggests->first();

        $withoutIds = array_unique(array_merge($favorites, $withoutIds));

        if (count($withoutIds) && $userId) {
            $key = array_search($userId, $withoutIds);
            if ($key >= 0) {
                unset($withoutIds[$key]);
                $withoutIds[] = $userId;
            }
        }

        if ($userSuggest) {
            $appModeData['suggest']['withoutIds'] = array_unique(array_merge($isReset ? $favorites : $withoutIds, [(int)$userSuggest->id]));
            $recruiter->update(['app_mode_data' => $appModeData]);

            return  self::getUserInfoForListUser($this->user, [$userSuggest]);
        }
    }

    /**
     * @param $data
     * @return bool[]
     * @throws InputException
     * @throws Exception
     */
    public function favoriteUser($data)
    {
        $user = User::query()->where('id', $data['user_id'])->roleUser()->first();

        if ($user) {
            $recruiter = $this->user;
            $favoriteUser = $recruiter->favoriteUsers()->where('favorite_user_id', $user->id)->first();

            if ($favoriteUser) {
                return false;
            }

            try {
                DB::beginTransaction();

                FavoriteUser::create([
                    'user_id' => $recruiter->id,
                    'favorite_user_id' => $user->id
                ]);

                $recruiterJobIds = $recruiter->jobsOwnedTrashed()->pluck('job_postings.id')->toArray();
                $userFavoriteJobs = $user->favoriteJobs()
                    ->whereIn('job_posting_id', $recruiterJobIds)
                    ->with([
                        'jobPostingTrashed',
                        'jobPostingTrashed.storeTrashed'
                    ])
                    ->get();

                $userNotifyData = [];

                foreach ($userFavoriteJobs as $favoriteJob) {
                        $userNotifyData[] = ([
                            'user_id' => $user->id,
                            'notice_type_id' => Notification::TYPE_MATCHING_FAVORITE,
                            'noti_object_ids' => json_encode([
                                'user_id' => $recruiter->id,
                                'job_id' => $favoriteJob->jobPostingTrashed->id,
                                'store_id' => $favoriteJob->jobPostingTrashed->storeTrashed->id,
                            ]),
                            'title' => trans('notification.N010.title', [
                                'store_name' => $favoriteJob->jobPostingTrashed->storeTrashed->name,
                            ]),
                            'content' => trans('notification.N010.content', [
                                'store_name' => $favoriteJob->jobPostingTrashed->storeTrashed->name,
                            ]),
                            'created_at' => now(),
                        ]);
                    }

                if ($userFavoriteJobs->isNotEmpty()) {
                        $userNotifyData[] = [
                            'user_id' => $recruiter->id,
                            'notice_type_id' => Notification::TYPE_MATCHING_FAVORITE,
                            'noti_object_ids' => json_encode([
                                'store_id' => null,
                                'application_id' => null,
                                'user_id' => $user->id,
                                'job_posting_id' => null,
                            ]),
                            'title' => trans('notification.N009.title', [
                                'user_name' => sprintf('%s %s', $user->first_name, $user->last_name),
                            ]),
                            'content' => trans('notification.N009.content', [
                                'user_name' => sprintf('%s %s', $user->first_name, $user->last_name),
                            ]),
                            'created_at' => now(),
                        ];
                    }

                if (count($userNotifyData)) {
                    Notification::insert($userNotifyData);
                }

                DB::commit();

                return [
                    'is_matching' => count($userNotifyData) > 0
                ];
            } catch (Exception $e) {
                DB::rollBack();
                throw $e;
            }
        }

        throw new InputException(trans('response.invalid'));
    }

    /**
     * @param $data
     * @return false[]
     * @throws InputException
     */
    public function unfavoriteUser($data)
    {
        $user = User::query()->withTrashed()->where('id', $data['user_id'])->roleUser()->first();

        if ($user) {
            $recruiter = $this->user;

            $recruiter->favoriteUsers()->where('favorite_user_id', $user->id)->delete();

            return [
                'is_matching' => false
            ];
        }

        throw new InputException(trans('response.invalid'));
    }

    /**
     * Get user info for list user
     *
     * @param $recruiter
     * @param $userList
     * @return array
     */
    public static function getUserInfoForListUser($recruiter, $userList)
    {
        $recruiterFavoriteUser = $recruiter->favoriteUsers->pluck('favorite_user_id')->toArray();
        $recruiterJobIds = $recruiter->jobsOwned->pluck('job_postings.id')->toArray();
        $jobMasterData = UserHelper::getJobMasterData();
        $userArr = [];

        foreach ($userList as $user) {
            $userDesiredCondition = $user->desiredConditionUser;
            $userFavoriteJobs = $user->favoriteJobs->pluck('job_posting_id')->toArray();

            if (isset($userDesiredCondition->job_type_ids)) {
                $user->job_types = JobHelper::getTypeName(
                    $userDesiredCondition->job_type_ids,
                    $jobMasterData['masterJobTypes']
                );
            }
            if (isset($userDesiredCondition->job_experience_ids)) {
                $user->job_experiences = JobHelper::getTypeName(
                    $userDesiredCondition->job_experience_ids,
                    $jobMasterData['masterJobExperiences']
                );
            }

            if (isset($userDesiredCondition->job_feature_ids)) {
                $user->job_features = JobHelper::getTypeName(
                    $userDesiredCondition->job_feature_ids,
                    $jobMasterData['masterJobFeatures']
                );
            }

            if (isset($userDesiredCondition->work_type_ids)) {
                $user->work_types = JobHelper::getTypeName(
                    $userDesiredCondition->work_type_ids,
                    $jobMasterData['masterWorkTypes']
                );
            }

            if (isset($userDesiredCondition->province_ids)) {
                $user->provinces = UserHelper::getListProvinceNames(
                    $userDesiredCondition->province_ids,
                    $jobMasterData['masterProvinces']
                );
            }

            $user->favorite = !!in_array($user->id, $recruiterFavoriteUser);
            $user->matching = array_intersect($recruiterJobIds, $userFavoriteJobs);

            $userArr[$user->id] = $user;
        }//end foreach

        return $userArr;
    }
}
