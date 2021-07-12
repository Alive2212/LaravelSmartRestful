<?php

namespace Alive2212\LaravelSmartRestful;

use Alive2212\LaravelOnionPattern\BasePattern;
use Alive2212\LaravelQueryHelper\QueryHelper;
use Alive2212\LaravelRequestHelper\RequestHelper;
use Alive2212\LaravelStringHelper\StringHelper;
use App\Http\Controllers\Controller;
use Alive2212\LaravelSmartResponse\ResponseModel;
use Alive2212\LaravelSmartResponse\SmartResponse;
use Exception;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use stdClass;
use Throwable;

abstract class SmartCrudController extends Controller
{
    /**
     * to use this class
     * create message list as messages in message file
     * override __constructor and define your model
     * define your rules for index,store and update
     */

    protected $forceUpdateKey = 'smart_restful_force_update';

    /**
     * base pattern
     */
    use BasePattern;

    /**
     * store tag to store data of request
     *
     * @var string
     */
    protected $cachedTag = 'response_cached';

    /**
     * store tag to store data of request
     *
     * @var string
     */
    protected $storeTag = 'response_store';

    /**
     * function name tag for pass data to response
     *
     * @var string
     */
    protected $functionNameResponseTag = 'response_function';

    /**
     * data tag for pass data to response
     *
     * @var string
     */
    protected $dataResponseTag = 'response_data';

    /**
     * @var string
     */
    protected $shortTagName = 'smart_restful';

    /**
     * permission types 'admin'|'branch'|'own'|'guest'
     *
     * @var string
     */
    protected $defaultPermissionType = 'admin';

    /**
     * permission types 'admin'|'branch'|'own'|'guest'
     *
     * @var string
     */
    protected $groupTitle = null;

    /**
     * @var null
     */
    protected $defaultLocaleClass = null;

    /**
     * @var string
     */
    protected $localPrefix = 'controller';

    /**
     * @var int
     */
    protected $DEFAULT_RESULT_PER_PAGE = 15;

    /**
     * @var int
     */
    protected $DEFAULT_PAGE_NUMBER = 1;

    /**
     * @var array
     */
    protected $pivotFields = [];

    /**
     * @var array
     */
    protected $uniqueFields = [];

    /**
     * @var bool|string
     */
    protected $modelName;

    /**
     * @var string
     */
    protected $messagePrefix = 'messages.api.v1.';

    /**
     * this model
     */
    protected $model;

    /**
     * index request validator rules
     *
     * @var array
     */
    protected $indexValidateArray = [
        //
    ];

    /**
     * array of relationship for eager loading
     *
     * @var array
     */
    protected $indexRelations = [
        //
    ];

    /**
     * array of relationship for eager loading
     *
     * @var array
     */
    protected $showRelations = [
        //
    ];

    /**
     * array of relationship for eager loading
     *
     * @var array
     */
    protected $updateLoad = [
        //
    ];

    /**
     * array of relationship for eager loading
     *
     * @var array
     */
    protected $storeLoad = [
        //
    ];

    /**
     * store request validator rules
     *
     * @var array
     */
    protected $storeValidateArray = [
        //
    ];

    /**
     * update request validator rules
     *
     * @var array
     */
    protected $updateValidateArray = [
        //
    ];

    /**
     * @var array
     */
    protected $middlewareParams = [];

    /**
     * @var array
     */
    protected $storeOrUpdateFields = [];

    /**
     * this variable for create and assigned to model when put in request in Store & Update method
     * @var array
     */
    protected $associates = [];

    /**
     * This variable for model key on query string id
     * @var null
     */
    protected $modelKey = null;

    /**
     * key for split
     *
     * @var string
     */
    protected $manyToManyKeyDelimiter = ":";

    /**
     * key for split parameters
     *
     * @var string
     */
    protected $manyToManyParameterKeyDelimiter = ",";

    /**
     * key for remove all last relations
     *
     * @var string
     */
    protected $manyToManySyncKey = "sync";

    /**
     * defaultController constructor.
     */
    public function __construct()
    {
        // init controller
        $this->initController();

        // set local language
        $this->setLocale();
    }

    /**
     * @return void
     */
    abstract public function initController();

    /**
     * @param string $functionName
     * @param Request $request
     * @return ResponseModel
     */
    public function beforeResponse(string $functionName, $responseModel, Request $request): ResponseModel
    {
        return $responseModel;
    }

    public function getSum($item, array $methods, $column)
    {
        if (count($methods) < 1) return 0;
        if (count($methods) == 1) {
            if ($item instanceof Collection) {
                return $item->sum($column);
            } else {
                $method = $methods[0];
                return $item->$method->$column;
            }
        }

        foreach ($methods as $index => $method) {
            $usableMethods = array_slice($methods, 0, $index + 1);

            $workingItem = $item;
            foreach ($usableMethods as $uMethod) {
                if (isset($workingItem->$uMethod)) {
                    $workingItem = $workingItem->$uMethod;
                }
            }

            if ($workingItem instanceof Collection) {
                $newUsableMethods = array_slice($methods, $index);
                if (count($newUsableMethods) === 1) {
                    return $workingItem->sum($column);
                }
                $workingItem->sum(function ($newItem) use ($newUsableMethods, $column) {
                    return $this->getSum($newItem, $newUsableMethods, $column);
                });

            }
        }
        $workingItem = $item;
        foreach ($methods as $uMethod) {
            if (isset($workingItem->$uMethod)) {
                $workingItem = $workingItem->$uMethod;
            }
        }
        if ($workingItem instanceof Collection) {
            return $item->sum(implode('.', $methods) . "." . $column);
        } else {
            return $workingItem->$column;
        }
    }

    /**
     * Display a listing of the resource.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function index(Request $request): JsonResponse
    {
        // handle permission
//        list($request, $filters) = $this->handlePermission(__FUNCTION__, $request);

        // create response model
        $response = new ResponseModel();

        // Get Page Size
        $pageSize = $this->getPageSize($request);
        $request['page_size'] = $pageSize;

        //set default ordering
        $orderBy = $this->getOrderBy($request);
        $request['order_by'] = $orderBy;

        $validationErrors = $this->checkRequestValidation($request, $this->indexValidateArray);
        if ($validationErrors != null) {
            if (env('APP_DEBUG', false)) {
                $response->setData(collect($validationErrors->toArray()));
            }
            $response->setStatusCode(422);
            $response->setMessage($this->getTrans(__FUNCTION__, 'validation_failed'));
            $response->setError($validationErrors->toArray());
            return SmartResponse::response($response);
        }


        try {
            $data = $request->get('query') != null ?
                $this->model
                    ->whereKey(collect($this->model
                        ->search(($request->get('query')))
                        ->raw())->get('ids')) :
                $this->model;

            // load relations
            $relations = $this->getRequestRelations($request);
            $data = $this->addRelationToData($data, $this->getArrayWithPriority($this->indexRelations, $relations));

            // filters by
            if (!is_null($request->get('filters'))) {
                $data = (new QueryHelper())->smartDeepFilter($data, (new RequestHelper())->getCollectFromJson($request['filters'])->toArray());
            }

            // order by
            if (!is_null($request->get('order_by'))) {
                $data = (new QueryHelper())->orderBy($data, (new RequestHelper())->getCollectFromJson($request['order_by']));
            }

            $finalData = collect($data->paginate($pageSize));

            // Summation
            if ($request->get('summations') != null) {
                $summations = json_decode($request->get('summations'));
                $summationsResults = [];

                foreach ($summations as $summationIndex => $summationItems) {
                    $query = clone $data;
//                    $query->paginate(9999999);
                    $methods = explode(".", $summationItems);
                    $column = array_pop($methods);
                    $relation = implode('.', $methods);

                    $summaryResult = $query->with($relation)->whereHas($relation)->limit(9999999)->offset(0)->get()->sum(function ($item) use ($methods, $column) {
                        return $this->getSum($item, $methods, $column);
                    });
                    $summationsResults[$summationItems] = $summaryResult;
                }
                $finalData['summations'] = $summationsResults;
            }

            // Set Response Data
            $response->setData($finalData);


            $response->setMessage($this->getTrans(__FUNCTION__, 'successful'));

            // assign something before response
            $response = $this->beforeResponse(__FUNCTION__, $response, $request);

            return SmartResponse::response($response);

        } catch (QueryException $exception) {

            // return response
            $response->setData(collect($exception->getMessage()));
            $response->setError(['query_exception' => $exception->getMessage()]);
            $response->setMessage($this->getTrans(__FUNCTION__, 'failed'));
            return SmartResponse::response($response);
        }
    }

    public function checkRequestValidation(Request $request, $validationArray)
    {

        $requestParams = $request->toArray();
        $validator = Validator::make($request->all(), $validationArray);
        if ($validator->fails()) {
            return $validator->errors();
        }
        return null;
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return JsonResponse
     */
    public function create()
    {
        // Create Response Model
        $response = new ResponseModel();

        // return response
        $response->setData(collect($this->model->getFillable()));
        $response->setMessage($this->getTrans('create', 'successful'));
        return SmartResponse::response($response);
    }

    /**
     * @param Request $request
     * @return Request
     */
    public function storeAfterValidation(Request $request): Request
    {
        return $request;
    }

    /**
     * @param Request $request
     * @return JsonResponse
     * @throws \Exception
     */
    public function store(Request $request): JsonResponse
    {
        $response = new ResponseModel();

        $validationErrors = $this->checkRequestValidation($request, $this->storeValidateArray);

        if ($validationErrors != null) {
            $response->setStatusCode(422);
            $response->setMessage($this->getTrans(__FUNCTION__, 'validation_failed'));
            $response->setError($validationErrors->toArray());
            return SmartResponse::response($response);
        }

        // Method for customize
        $request = $this->afterValidation(__FUNCTION__, $request);

        // init request for multiple model key
        if (is_array($this->model->getKeyName())) {
            foreach ($this->model->getKeyName() as $keyName) {
                $request->request->set($keyName, $request->$keyName);
            }
        }

        // Get all object of request
        $objects = $request->all();

        if (key_exists($this->forceUpdateKey, $objects)) {
            $this->forceUpdateOnStore = $objects[$this->forceUpdateKey];
            unset($objects[$this->forceUpdateKey]);
        }

        try {
            DB::beginTransaction();
            [$model, $resultObject] = $this->deepAssociation($this->model, $objects);
            $response->setData(collect($resultObject));
            $response->setMessage($this->getTrans(__FUNCTION__, 'success'));
            $response->setStatusCode(201);
            // Assign something before response
            $response = $this->beforeResponse(__FUNCTION__, $response, $request);
            DB::commit();
        } catch (Throwable $e) {
            DB::rollBack();
            $response->setError([$e->getMessage()]);
            $response->setMessage($this->getTrans(__FUNCTION__, 'failed'));
            $response->setStatusCode(409);
        }

        // Response
        return SmartResponse::response($response);
    }

    /**
     * @param array $arr
     * @return bool
     */
    function isAssoc(array $arr): bool
    {
        if (array() === $arr) return false;
        return array_keys($arr) !== range(0, count($arr) - 1);
    }

    /**
     * @param $model
     * @param array $objects
     * @return array
     */
    public function deepAssociation($model, array $objects): array
    {
        $modelKeys = $model->getModel()->getKeyName();
        if ($this->isAssoc($objects)) {
            [$model, $resultObject] = $this->createObjectsInDB($model, $modelKeys, $objects);
        } else {
            $resultObject = [];
            foreach ($objects as $object) {
                $userId = Auth::id();

                //add author id into the request if doesn't exist
                if (!Arr::has($object, 'author_id')) {
                    $object['author_id'] = $userId;
                }

                //add user id into the request if doesn't exist
                if (!Arr::has($object, 'user_id')) {
                    $object['user_id'] = $userId;
                }

                [$tmp, $resultObjectItem] = $this->createObjectsInDB($model, $modelKeys, $object);
                array_push($resultObject, $resultObjectItem);
            }

        }
        return [$model, $resultObject];
    }

    public function firstOrCreateAfterInitCondition($model, $modelKeys, $condition, $objectWithoutKey, $object)
    {
        return array($condition, $objectWithoutKey);
    }

    /**
     * @param $model
     * @param $modelKeys
     * @param array $object
     * @return mixed
     */
    private function firstOrCreateObject($model, $modelKeys, array $object)
    {
        if ($model instanceof Model) {
            $modelFields = $model->getFillable();
        } else {
            $modelFields = $model->getModel()->getFillable();
        }

        list($condition, $objectWithoutKey) = $this->getConditionByModelKeys($modelKeys, $object);

        $condition = $this->addConditionByUniqueFields($model, $objectWithoutKey, $condition);

        list($condition, $objectWithoutKey) = $this->firstOrCreateAfterInitCondition(
            $model,
            $modelKeys,
            $condition,
            $objectWithoutKey,
            $objectWithoutKey
        );
        if (
            is_array($condition) &&
            count($condition)
        ) {
            $currentModelObject = $model->getModel()->where($condition)->first();
            if ($currentModelObject == null) {
                if ($model instanceof MorphOne) {
                    $modelObject = $model->where($condition)->first();
                    if (!$modelObject) {
                        $modelObject = $model->create($object);
                    }
                } else {
                    $firstOrCreateCondition = [];
                    foreach ($condition as $item) {
                        Arr::set($firstOrCreateCondition, $item[0] , $item[2]);
                    }
                    $modelObject = $model->firstOrCreate($firstOrCreateCondition, $object);
                }
                $modelObject->update($object);
            } else {
                $currentModelObject->update(collect($object)->only($modelFields)->toArray());
                $modelObject = $currentModelObject;
                if ($model instanceof HasMany) {
                    $model->save($currentModelObject);
                } elseif ($model instanceof BelongsToMany) {
                    $id = $currentModelObject[$modelKeys];
                    if (array_key_exists("pivot", $objectWithoutKey)) {
                        $pivotTitles = $objectWithoutKey["pivot"];
                        $model->attach($id, $pivotTitles);
                    } else {
                        $model->attach($id);
                    }
                } elseif ($model instanceof MorphOne) {
                    $model->save($currentModelObject);
                } elseif ($model instanceof MorphMany) {
                    $model->save($currentModelObject);
                } elseif ($model instanceof HasOne) {
                    if (count($model->first()->toArray()) > 0) {
                        $model->save($currentModelObject);
                    }
                } elseif ($model instanceof BelongsTo) {
                    if ($currentModelObject->get()->toArray() > 0) {
                        $model->associate($currentModelObject);
                        $model->getParent()->save();
                    }
                } else {
                    if (count($model->get()->toArray()) > 0) {
                        return $modelObject;
                    }
                }
            }
        } else {
            $modelObject = $model->create(collect($object)->only($modelFields)->toArray());
        }
        return $modelObject;
    }

    /**
     * @param $object
     * @param $modelObject
     * @return array
     */
    private function findAnotherObjectForAssociation($object, $modelObject): array
    {
        $model = null;
        $resultObject = $modelObject->toArray();
        foreach ($object as $objectKeyWithParams => $objectValue) {
            if (is_array($objectValue) && !key_exists($objectKeyWithParams, $modelObject->getFillable())) {
                $objectKeys = explode($this->manyToManyKeyDelimiter, $objectKeyWithParams);
                $objectKey = $objectKeys[0];
                unset($objectKeys[0]);
                if (count($objectKeys) > 0) {
                    $objectKeys = explode($this->manyToManyParameterKeyDelimiter, $objectKeys[1]);
                }
                if (method_exists($modelObject, $objectKey)) {
                    $relativeModel = $modelObject->$objectKey();
                    if (
                        $relativeModel instanceof BelongsToMany &&
                        count($objectValue)
                    ) {
                        if (in_array($this->manyToManySyncKey, $objectKeys)
                        ) {
                            $relativeModel->sync([], true);
                        }
                        if (!$this->isAssoc($objectValue)) {
                            $detachIds = [];
                            foreach ($objectValue as $item) {
                                //TODO it should dynamic for id or another key/keys of models
                                if (array_key_exists('pivot', $item)) {
                                    array_push($detachIds, $item['id']);
                                }
                            }
                            if (count($detachIds)) {
                                $relativeModel->detach($detachIds);
                            }
                        }
                    }
                    if (
                        $relativeModel instanceof MorphMany &&
                        count($objectValue)
                    ) {
                        if (in_array($this->manyToManySyncKey, $objectKeys)
                        ) {
                            $tmp = $relativeModel->delete();
                        }
                    }

                    [$model, $resultObjectItems] = $this->deepAssociation($relativeModel, $objectValue);
                    $resultObject[$objectKey] = $resultObjectItems;
                };
            }
        }
        return [$model, $resultObject];
    }

    /**
     * @param array $object
     * @param $modelKey
     * @return array
     */
    private function fillConditionByModelKey(array $object, $modelKey): array
    {
        $condition = [];
        if (array_key_exists($modelKey, $object)) {
            array_push($condition, [
                $modelKey, '=', $object[$modelKey]
            ]);
            unset($object[$modelKey]);
        }
        return array($condition, $object);
    }

    /**
     * @param $modelKeys
     * @param array $object
     * @return array
     */
    private function getConditionByModelKeys($modelKeys, array $object): array
    {
        $condition = [];
        if (is_array($modelKeys)) {
            foreach ($modelKeys as $modelKey) {
                list($condition, $object) = $this->fillConditionByModelKey($object, $modelKey);
            }
        } else {
            $modelKey = $modelKeys;
            list($condition, $object) = $this->fillConditionByModelKey($object, $modelKey);
        }
        return array($condition, $object);
    }

    /**
     * @param $model
     * @param $modelKeys
     * @param array $object
     * @return array
     */
    private function createObjectsInDB($model, $modelKeys, array $object)
    {
        $modelObject = $this->firstOrCreateObject($model, $modelKeys, $object);
        [$model, $resultObject] = $this->findAnotherObjectForAssociation($object, $modelObject);
        return [$model, $resultObject];
    }

    /**
     * @param string $functionName
     * @param Request $request
     * @return Request
     */
    public function afterValidation(string $functionName, Request $request): Request
    {
        return $request;
    }

    /**
     * Display the specdefaultied resource.
     *
     * @param Request $request
     * @param int $id
     * @return JsonResponse
     */
    public function show(Request $request): JsonResponse
    {
        $filters = [];
        // handle permission
        // Create Response Model
        $response = new ResponseModel();

        try {
            $response->setMessage($this->getTrans(__FUNCTION__, 'successful'));

            // add filter to get desired record
            if (is_array($this->model->getKeyName())) {
                foreach ($this->model->getKeyName() as $keyName) {
                    array_push($filters,
                        [$keyName, '=', $request->$keyName]
                    );
                }
            } else {
                $queryStringKey = $this->modelKey ? $this->modelKey : Str::singular(strtolower($this->model->getTable()));
                $index = $request->$queryStringKey;
                $keyName = $this->model->getKeyName();
                array_push($filters,
                    [$keyName, '=', $index]
                );
            }

            // load relations
            $relations = $this->getRequestRelations($request);
            $relations = $this->getArrayWithPriority($this->showRelations, $this->indexRelations, $relations);
            $data = $this->model
                ->where($filters)
                ->with($relations)
                ->get();
            $response->setData($data);

        } catch (ModelNotFoundException $exception) {
            $response->setError($exception->getCode());
            $response->setMessage($this->getTrans(__FUNCTION__, 'failed'));
            if (env('APP_DEBUG', false)) {
                $response->setData(collect($exception->getMessage()));
            }
        }

        if ($response->getData()->count() == 0) {
            $response->setStatusCode(404);
            $response->setError(['model_not_found_exception' => 'model not found']);
            $response->setMessage($this->getTrans(__FUNCTION__, 'not_found'));
        }

        return SmartResponse::response($response);
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function edit(Request $request): JsonResponse
    {
        $filters = [];
//        // handle permission
//        $filters = $this->handlePermission(__FUNCTION__);

        // Create Response Model
        $response = new ResponseModel();

        try {
            $response->setMessage($this->getTrans(__FUNCTION__, 'successful'));

            $queryStringKey = $this->modelKey ? $this->modelKey : Str::singular(strtolower($this->model->getTable()));
            $index = $request->$queryStringKey;

            // add filter to get desired record
            array_push($filters,
                [$this->model->getKeyName(), '=', $index]
            );
            $data = $this->model
                ->where($filters)
                ->with(collect($this->showRelations)->count() == 0 ? $this->indexRelations : $this->showRelations)
                ->get();
            $response->setData($data);

        } catch (ModelNotFoundException $exception) {
            $response->setError($exception->getCode());
            $response->setMessage($this->getTrans(__FUNCTION__, 'failed'));
            if (env('APP_DEBUG', false)) {
                $response->setData(collect($exception->getMessage()));
            }

        }

        return SmartResponse::response($response);
    }

    /**
     * Update the specified resource in storage.
     *
     * @param Request $request
     * @return JsonResponse
     * @throws \Exception
     */
    public function update(Request $request): JsonResponse
    {
        // add filter to get desired record
        if (!is_array($this->model->getKeyName())) {
            $queryStringKey = $this->modelKey ? $this->modelKey : Str::singular(strtolower($this->model->getTable()));
            $keyName = $this->model->getKeyName();
            $request[$keyName] = $request->$queryStringKey;
        }
        $request[$this->forceUpdateKey] = true;
        return $this->store($request);
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function destroy(Request $request)
    {
        // Create Response Model
        $response = new ResponseModel();

        try {
            $queryStringKey = $this->modelKey ? $this->modelKey : Str::singular(strtolower($this->model->getTable()));
            $index = $request->$queryStringKey;

            $response->setData(collect(['row_affected' => (int)$this->model->findOrFail($index)->delete()]));
            $response->setMessage($this->getTrans(__FUNCTION__, 'successful'));

        } catch (ModelNotFoundException $exception) {
            $response->setData(collect([]));
            $response->setMessage($this->getTrans(__FUNCTION__, 'not_found'));
            $response->setStatusCode(404);
            $response->setError(['model_not_found' => $exception->getMessage()]);
        }

        return SmartResponse::response($response);
    }

    /**
     * @param $method
     * @param $status
     * @return array|\Illuminate\Contracts\Translation\Translator|null|string
     */
    public function getTrans($method, $status)
    {
        if (is_null($this->defaultLocaleClass)) {
            $className = Arr::last(explode('\\', get_class($this)));
        } else {
            $className = $this->defaultLocaleClass;
        }
        return trans(
            'laravel_smart_restful::' .
            $this->localPrefix . '.' .
            ($className === '' ? '' : ($className . '.')) .
            $method . '.' .
            $status
        );
    }

    /**
     * set locale
     */
    public function setLocale()
    {
        if (isset($_SERVER['HTTP_ACCEPT_LANGUAGE'])) {
            app('translator')->setLocale($_SERVER['HTTP_ACCEPT_LANGUAGE']);
        }
    }

    /**
     * @param $functionName
     * @param Request|null $request
     * @param null $id
     * @param array $params
     * @return mixed
     */
    public function handlePermission($functionName, Request $request = null, $id = null, $params = [])
    {
        // define filter
        $filters = null;

        // init permission
        $request = $this->initPermissionTag($request);

        // init method name with
        $methodName =
            'handle' .
            (new StringHelper())->upperFirstLetter($functionName) .
            (new StringHelper())->upperFirstLetter($this->defaultPermissionType) .
            'Permission';

        // return response
        return $this->$methodName($request, $id);
    }

    /**
     * @param Request $request
     * @param $id
     * @return Request
     */
    public function handleIndexAdminPermission(Request $request, $id = null): Request
    {
        return $request;
    }

    /**
     * @param Request $request
     * @param null $id
     * @return Request
     */
    public function handleIndexBranchPermission(Request $request, $id = null): Request
    {
        $request = $this->getFilters($request);
        $request = $this->putGroupFilter($request);
        $request = $this->putJsonFilters($request);
        return $request;
    }

    /**
     * @param Request $request
     * @param $id
     * @return Request
     */
    public function handleIndexOwnPermission(Request $request, $id = null): Request
    {
        $request = $this->getFilters($request);
        $request = $this->putOwnerFilter($request, auth()->id());
        $request = $this->putGroupFilter($request);
        $request = $this->putJsonFilters($request);
        return $request;
    }

    public function handleIndexGuestPermission(Request $request, $id)
    {
        $request = $this->getFilters($request);
        $request = $this->putOwnerFilter($request);
        $request = $this->putGroupFilter($request);
        $request = $this->putJsonFilters($request);
        return $request;
    }

    /**
     * @param Request $request
     * @param $id
     * @return array
     */
    public function handleStoreAdminPermission(Request $request, $id)
    {
        $request['group_id'] = null;

        $request['owner_id'] = auth()->id();

        return [$request, null];
    }

    /**
     * @param array $filters
     * @param $key
     * @param $operator
     * @param $value
     * @return array
     */
    public function addFilter(array $filters, $key, $operator, $value)
    {
        if (count(explode('.', $key)) == 1 && $this->filedExist($key)) {
            array_push($filters,
                [
                    'key' => $key,
                    'operator' => $operator,
                    'value' => $value,
                ]
            );
        }
        return $filters;
    }

    /**
     * @param $key
     * @return bool
     */
    public function filedExist($key)
    {
        return in_array($key, $this->model->getFillable());
    }

    /**
     * @return string
     */
    public function getLocalPrefix(): string
    {
        return $this->localPrefix;
    }

    /**
     * @param string $localPrefix
     */
    public function setLocalPrefix(string $localPrefix)
    {
        $this->localPrefix = $localPrefix;
    }

    /**
     * @param Request $request
     * @return Request
     */
    public function initPermissionTag(Request $request): Request
    {
        if (!is_null($request)) {
            if ($request->has($this->getRequestTagName('permission_type'))) {
                $this->defaultPermissionType = $request[$this->getRequestTagName('permission_type')];
            }
        }
        return $request;
    }

    public function getRequestTagName($name): string
    {
        return $this->shortTagName . '_' . $name;
    }

    /**
     * get filter if exist
     *
     * @param Request $request
     * @return Request
     */
    public function getFilters(Request $request): Request
    {
        // check for exist filters in request and get filters
        if (isset($request['filters'])) {
            $filters = json_decode($request['filters'], true);
        } else {
            $filters = [];
        }

        $request[$this->getRequestTagName('filter')] = $filters;

        return $request;
    }

    /**
     * @param Request $request
     * @return Request
     */
    public function putGroupFilter(Request $request): Request
    {
        if (!is_null($this->groupTitle)) {
            $request[$this->getRequestTagName('filter')] = $this->addFilter(
                $request[$this->getRequestTagName('filter')],
                'group.title',
                '=',
                $this->groupTitle
            );
        }
        return $request;
    }

    /**
     * @param Request $request
     * @param $userId
     * @return Request
     */
    public function putOwnerFilter(Request $request, $userId = null): Request
    {
        $request[$this->getRequestTagName('filter')] =
            $this->addFilter(
                $request[$this->getRequestTagName('filter')],
                'owner_id',
                '=',
                $userId
            );
        return $request;
    }

    /**
     * @param Request $request
     * @return Request
     */
    public function putJsonFilters(Request $request): Request
    {
        $request['filters'] = json_encode($request[$this->getRequestTagName('filter')]);
        return $request;
    }

    /**
     * @return mixed
     */
    public function getModel(): Model
    {
        return $this->model;
    }

    /**
     * @param Model $model
     * @return $this
     */
    public function setModel(Model $model): SmartCrudController
    {
        $this->model = $model;
        return $this;
    }

    /**
     * @param Request $request
     * @return JsonResponse
     */
    public function successfulResponse(Request $request): JsonResponse
    {
        $response = new ResponseModel();
        if (!is_null($request->get($this->dataResponseTag))) {
            $response->setData(collect($request[$this->dataResponseTag]));
        };
        $response->setMessage($this->getTrans(
            $request->get($this->functionNameResponseTag),
            'successful'
        ));
        return SmartResponse::response($response);
    }

    /**
     * @param Request $request
     * @return JsonResponse
     */
    public function failedResponse(Request $request): JsonResponse
    {
        $response = new ResponseModel();
        $this->setResponseData($request, $response);
        $response->setMessage($this->getTrans(
            $request->get($this->functionNameResponseTag),
            'failed'
        ));
        return SmartResponse::response($response);
    }

    /**
     * @param Request $request
     * @param $response
     */
    public function setResponseData(Request $request, $response)
    {
        if (!is_null($request->get($this->dataResponseTag))) {
            $data = $request->get($this->dataResponseTag);
            if (method_exists($data, 'getCode')) {
                $response->setError($data->getCode());
            }
            if (method_exists($data, 'getResponse')) {
                $response->setData(collect(json_decode(
                    $data->getResponse()
                        ->getBody()
                        ->getContents()
                )));
            }
        };
    }

    /**
     * @param Request $request
     * @return int
     */
    public function getPageSize(Request $request): int
    {
//set page size
        if (isset($request['page_size'])) {
            return $request['page_size'];
        } else {
            return $this->DEFAULT_RESULT_PER_PAGE;
        }

//        if (!isset($request->toArray()['page']['size'])) {
//            $pageSize = $this->DEFAULT_RESULT_PER_PAGE;
//        } elseif (($request->get('page')['size']) == 0) {
//            $pageSize = $this->DEFAULT_RESULT_PER_PAGE;
//        } else {
//            $pageSize = $request->get('page')['size'];
//        }
//        return $pageSize;
    }

    /**
     * @param Request $request
     * @return mixed|string
     */
    public function getOrderBy(Request $request)
    {
        $orderBy = $request->get('order_by');
        if (is_null($orderBy) || $orderBy == "") {
            $orderBy = "[\"" . $this->model->getKeyName() . "\",\"DESC\"]";
        }
        return $orderBy;
    }

    /**
     * @param Request $request
     * @param array $default
     * @return array|mixed
     */
    public function getRequestRelations(Request $request, array $default = [])
    {
        $requestRelations = $request->get('relations');
        if (is_null($requestRelations) || $requestRelations == "") {
            $relations = $default;
        } else {
            $relations = json_decode($requestRelations);
        }
        return $relations;
    }

    /**
     * @param $data
     * @param array $relations
     * @return mixed
     */
    public function addRelationToData($data, array $relations)
    {
        foreach ($relations as $relation) {
            if (is_array($relation) || $relation instanceof stdClass) {
                foreach ($relation as $relationKey => $relationFilter) {
                    $data = $data->with([$relationKey => function ($query) use ($relationFilter) {
                        $query = (new QueryHelper())->smartDeepFilter($query, $relationFilter);
                    }]);
                }
            } else {
                $data = $data->with($relation);
            }
        }
        return $data;
    }

    public function getArrayWithPriority(array ...$arrays)
    {
        foreach ($arrays as $array) {
            if (count($array)) {
                return $array;
            }
        }
        return [];
    }

    /**
     * @param $model
     * @param $objectWithoutKey
     * @param $condition
     * @return mixed
     */
    private function addConditionByUniqueFields($model, $objectWithoutKey, $condition)
    {
        if (property_exists($model->getModel(), 'uniqueFields')) {
            if ($model->getModel()->uniqueFields == null) {
                return $condition;
            }
            foreach ($model->getModel()->uniqueFields as $uniqueField) {
                if (array_key_exists($uniqueField, $objectWithoutKey)) {
                    array_push($condition, [$uniqueField, "=", $objectWithoutKey[$uniqueField]]);
                }
            }
        }
        return $condition;
    }
}
