<?php

namespace Alive2212\LaravelSmartRestful;

use Illuminate\Auth\Authenticatable;
use Laravel\Lumen\Auth\Authorizable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Contracts\Auth\Authenticatable as AuthenticatableContract;
use Illuminate\Contracts\Auth\Access\Authorizable as AuthorizableContract;
use Laravel\Passport\HasApiTokens;
use Laravel\Scout\Searchable;

class BaseAuthLumenModel extends Model implements AuthenticatableContract, AuthorizableContract
{
    use Authenticatable, Authorizable,Searchable,HasApiTokens;

    /**
     * default relations of method
     *
     * @var array
     */
    protected $relation = [];

    /**
     * searchable columns in this model
     *
     * @var array
     */
    protected $searchableColumns = [];

    /**
     * searchable columns of one model into this model
     *
     * @var array
     */
    protected $searchableInnerColumns = [];

    /**
     * searchable columns of many model into this model
     *
     * @var array
     */
    protected $searchableManyInnerColumns = [];

    /**
     * Get the indexable data array for the model.
     *
     * @return array
     */
    public function toSearchableArray()
    {
        if (!sizeof($this->searchableColumns)) {
            $result = collect($this);
        } else {
            $result = collect([
                'id' => $this['id'],
            ]);
            foreach ($this->searchableColumns as $searchableColumn) {
                $result->put($searchableColumn, collect($this)->get($searchableColumn));
            }
        }
        if (sizeof($this->searchableInnerColumns)) {
            foreach ($this->searchableInnerColumns as $searchableInnerColumnKey => $searchableInnerColumnValue) {
                $searchableInnerColumnKey = explode('-', $searchableInnerColumnKey)[0];
                $innerModels = explode('.', $searchableInnerColumnKey);
                $values = $this;
                foreach ($innerModels as $innerModel) {
                    $values = $values->{$innerModel};
                }
                $values = collect($values);
                $result->put($searchableInnerColumnKey . '-' . $searchableInnerColumnValue,
                    collect($values)->get($searchableInnerColumnValue));
            }
        }
        if (sizeof($this->searchableManyInnerColumns)) {
            foreach ($this->searchableManyInnerColumns as $searchableInnerColumnKey => $searchableInnerColumnValue) {
                $searchableInnerColumnKey = explode('-', $searchableInnerColumnKey)[0];
                $innerModels = explode('.', $searchableInnerColumnKey);
                $values = $this;
                foreach ($innerModels as $innerModel) {
                    $values = $values->{$innerModel};
                }
                $values = collect($values);
                $counter = 0;
                foreach ($values as $value) {
                    $result->put($searchableInnerColumnKey . '-' . $searchableInnerColumnValue . '-' . $counter,
                        collect($value)->get($searchableInnerColumnValue));
                    $counter++;
                }
            }
        }
        return $result->toArray();
    }

    /**
     * Determine if the entity has a given ability.
     *
     * @param  string $ability
     * @param  array|mixed $arguments
     * @return bool
     */
    public function can($ability, $arguments = [])
    {
        // TODO: Implement can() method.
    }
}




