<?php

namespace App\Repositories;

use App\Repositories\Contracts\BaseRepositoryInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

abstract class BaseRepository implements BaseRepositoryInterface
{
    protected Model $model;

    public function __construct(Model $model)
    {
        $this->model = $model;
    }

    public function query(): Builder
    {
        return $this->model->newQuery();
    }

    public function all(array $with = [], array $conditions = [])
    {
        $query = $this->model::with($with);

        foreach ($conditions as $condition) {
            $query->where(...$condition);
        }

        return $query->get();
    }

    public function paginate(
        array $with = [],
        int $page = 10,
        array $conditions = [],
        int $skip = 0,
        string $orderBy = 'id',
        string $direction = 'desc',
        ?int $limit = null
    ) {
        $query = $this->model::with($with);
    
        foreach ($conditions as $condition) {
            [$field, $operator, $value] = $condition;
    
            $upperOp = strtoupper($operator);
    
            // Cas WHERE IN
            if ($upperOp === 'IN' && is_array($value)) {
                $query->whereIn($field, $value);
            }
            // Cas WHERE LIKE
            elseif ($upperOp === 'LIKE') {
                $query->where($field, 'LIKE', $value);
            }
            // Cas OR WHERE IN
            elseif ($upperOp === 'OR IN' && is_array($value)) {
                $query->orWhereIn($field, $value);
            }
            // Cas OR WHERE LIKE
            elseif ($upperOp === 'OR LIKE') {
                $query->orWhere($field, 'LIKE', $value);
            }
            // Cas normal (=, >, <, etc.)
            elseif (str_starts_with($upperOp, 'OR ')) {
                $realOp = substr($operator, 3); // enlève le "OR "
                $query->orWhere($field, $realOp, $value);
            }
            else {
                $query->where($field, $operator, $value);
            }
        }
    
        $query->orderBy($orderBy, $direction)
              ->skip($skip);
    
        if (!is_null($limit)) {
            $query->limit($limit);
        }
    
        return $query->paginate($page);
    }    

    public function find(string $id, array $with = [])
    {
        return $this->model->with($with)->findOrFail($id);
    }

    public function create(array $data): Model
    {
        return $this->model->create($data);
    }

    public function update(int $id, array $data): Model
    {
        $instance = $this->model->findOrFail($id);
        $instance->update($data);
        return $instance;
    }

    public function delete(string $id): bool
    {
        $obj = $this->model::find($id);
        return $obj->delete();
    }
}
