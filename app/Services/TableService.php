<?php

namespace App\Services;

use App\Helpers\StringHelper;
use App\Services\Contracts\TableContract;
use Closure;
use Exception;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Query\Expression;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use InvalidArgumentException;

abstract class TableService extends Service implements TableContract
{
    /**
     * @var integer
     */
    protected $perPage = 10;

    /**
     * @var string[]
     */
    protected $orderDirMap = [
        'asc' => 'asc',
        'desc' => 'desc',
    ];

    /**
     * [
     *   field1,
     *   field2
     * ]
     *
     * @var string[]
     */
    protected $searchables = [];

    /**
     * [
     *    columnKey => fieldName
     * ]
     *
     * @var string[]
     */
    protected $orderables = [];

    /**
     * [
     *    columnKey => filterFunction
     * ]
     *
     * @var string[]
     */
    protected $filterables = [];

    /**
     * @var boolean
     */
    protected $isPaginate = true;

    /**
     * @var Builder|\Illuminate\Database\Query\Builder
     */
    protected $query;

    /**
     * @return Builder|\Illuminate\Database\Query\Builder
     */
    protected function currentQuery()
    {
        if (!$this->query) {
            $this->newQuery();
        }

        return $this->query;
    }

    /**
     * Get data
     *
     * @param $search
     * @param $orders
     * @param $filters
     * @param null $perPage
     * @return Collection|LengthAwarePaginator
     */
    public function data($search, $orders, $filters, $perPage = null)
    {
        $perPage = $perPage ?? $this->perPage;
        $query = $this->buildQuery($search, $orders, $filters);
        $currentRouteUri = Route::getCurrentRoute()->uri();

        switch ($currentRouteUri) {
            case 'user/job':
            case 'recruiter/jobs':
                $query->orderBy('released_at', 'desc');
                break;
            case 'recruiter/users':
                $query->orderBy('users.created_at', 'desc');
                break;
        }

        if ($this->isPaginate) {
            return $query->paginate(intval($perPage));
        }

        return $query->get();
    }

    /**
     * Build Query
     *
     * @param $search
     * @param $orders
     * @param $filters
     * @return Builder|\Illuminate\Database\Query\Builder
     */
    public function buildQuery($search, $orders, $filters)
    {
        $query = $this->currentQuery();
        $this->applySearchToQuery($search, $query);
        $this->applyFilterToQuery($filters, $query);
        $this->applyOrderToQuery($orders, $query);

        return $query;
    }

    /**
     * @param $search
     * @param Builder $query
     * @return Builder|\Illuminate\Database\Query\Builder
     */
    protected function applySearchToQuery($search, Builder $query)
    {
        if (empty($search) || !is_string($search) || !count($this->searchables)) {
            return $query;
        }

        $search = StringHelper::escapeLikeSearch($search);
        $content = '%' . trim($search) . '%';
        $query->where(function ($q) use ($content) {
            foreach ($this->searchables as $searchable) {
                $q->orWhere($searchable, 'like', $content);
            }
        });

        return $query;
    }

    /**
     * @param array $filters
     * @param Builder|\Illuminate\Database\Query\Builder $query
     * @return Builder|\Illuminate\Database\Query\Builder
     */
    protected function applyFilterToQuery($filters, $query)
    {
        if (!is_array($filters)) {
            return $query;
        }

        foreach ($filters as $filter) {
            $issetFilter = !empty($filter['key']) && isset($filter['data']) && isset($this->filterables[$filter['key']]);
            if ($issetFilter && $filter['data'] !== null && $filter['data'] !== '') {
                $funName = $this->filterables[$filter['key']];
                if (method_exists($this, $funName)) {
                    $this->{$funName}($query, $filter, $filters);
                } else {
                    $this->defaultFilter($query, $filter, $filters);
                }
            }
        }

        return $query;
    }

    /**
     * @param Builder|\Illuminate\Database\Query\Builder $query
     * @param $filter
     * @param $filters
     * @return Builder|\Illuminate\Database\Query\Builder
     */
    protected function defaultFilter($query, $filter, $filters)
    {
        $query->where($this->filterables[$filter['key']], $filter['data']);

        return $query;
    }

    /**
     * @param $orders
     * @param Builder|\Illuminate\Database\Query\Builder $query
     * @return Builder|\Illuminate\Database\Query\Builder
     */
    protected function applyOrderToQuery($orders, $query)
    {
        if (!is_array($orders)) {
            return $query;
        }

        foreach ($orders as $order) {
            if (!empty($order['key']) && !empty($order['dir'])) {
                $dir = Str::lower($order['dir']);
                if (!empty($this->orderDirMap[$dir]) && !empty($this->orderables[$order['key']])) {
                    $query->orderBy($this->orderables[$order['key']], $this->orderDirMap[$dir]);
                }
            }
        }

        return $query;
    }

    /**
     * Set the relationships that should be eager loaded.
     *
     * @param string|array $relations
     * @param null $callback
     * @return $this
     */
    public function with($relations, $callback = null)
    {
        $this->currentQuery()->with(...func_get_args());

        return $this;
    }

    /**
     * Add an exists clause to the query.
     *
     * @param Closure $callback
     * @param string $boolean
     * @param bool $not
     * @return $this
     */
    public function whereExists(Closure $callback, $boolean = 'and', $not = false)
    {
        $this->currentQuery()->whereExists(...func_get_args());

        return $this;
    }

    /**
     * Add subselect queries to count the relations.
     *
     * @param mixed $relations
     * @return $this
     */
    public function withCount($relations)
    {
        $this->currentQuery()->withCount(...func_get_args());

        return $this;
    }

    /**
     * Add an "order by" clause to the query.
     *
     * @param Closure|Builder|\Illuminate\Database\Query\Builder|Expression|string $column
     * @param string $direction
     * @return $this
     *
     * @throws InvalidArgumentException
     */
    public function orderBy($column, $direction = 'asc')
    {
        $this->currentQuery()->orderBy(...func_get_args());

        return $this;
    }

    /**
     * Add a basic where clause to the query.
     *
     * @param Closure|string|array|Expression $column
     * @param mixed $operator
     * @param mixed $value
     * @param string $boolean
     * @return $this
     */
    public function where($column, $operator = null, $value = null, $boolean = 'and')
    {
        $this->currentQuery()->where(...func_get_args());

        return $this;
    }

    /**
     * @return $this
     */
    public function newQuery()
    {
        $this->query = $this->makeNewQuery();

        return $this;
    }

    /**
     * Add a join clause to the query.
     *
     * @param $table
     * @param Closure|string $first
     * @param null $operator
     * @param null $second
     * @param string $type
     * @param bool $where
     * @return $this
     */
    public function join($table, $first, $operator = null, $second = null, $type = 'inner', $where = false)
    {
        $this->currentQuery()->join(...func_get_args());

        return $this;
    }

    /**
     * Set the columns to be selected.
     *
     * @param array $columns
     * @return $this
     */
    public function select($columns = ['*'])
    {
        $this->currentQuery()->select(...func_get_args());

        return $this;
    }

    /**
     * Add a "group by" clause to the query.
     *
     * @param array|string ...$groups
     * @return $this
     */
    public function groupBy(...$groups)
    {
        $this->currentQuery()->groupBy(...func_get_args());

        return $this;
    }

    /**
     * Get Filterables
     *
     * @return array
     */
    public function getFilterables()
    {
        return $this->filterables;
    }

    /**
     * Parse Params
     *
     * @param $data
     * @return mixed|null
     */
    protected function parseParams($data)
    {
        try {
            return json_decode($data, true);
        } catch (Exception $e) {
            return null;
        }
    }

    /**
     * Parse String Params
     *
     * @param $str
     * @param string $separator
     * @param int $limit
     * @return mixed|null
     */
    protected function parseStringParams($str, $separator = ',', $limit = 100)
    {
        try {
            if (!is_string($str)) {
                return null;
            }

            $data = explode($separator, $str, $limit);
            $result = [];

            foreach ($data as $item) {
                $item = trim($item);
                if ($item === '') {
                    continue;
                }

                $result[] = $item;
            }

            return array_unique($result);
        } catch (Exception $e) {
            return null;
        }//end try
    }
}
