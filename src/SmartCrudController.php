<?php

namespace Alive2212\LaravelSmartRestful;

use Alive2212\LaravelOnionPattern\BasePattern;
use Alive2212\LaravelQueryHelper\QueryHelper;
use Alive2212\LaravelRequestHelper\RequestHelper;
use Alive2212\LaravelStringHelper\StringHelper;
use App\Http\Controllers\Controller;
use Alive2212\LaravelSmartResponse\ResponseModel;
use Alive2212\LaravelSmartResponse\SmartResponse;
use http\Message;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\MessageBag;
use Illuminate\Support\Str;


abstract class SmartCrudController extends Controller
{
    /**
     * to use this class
     * create message list as messages in message file
     * override __constructor and define your model
     * define your rules for index,store and update
     */

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
     * @param ResponseModel $responseModel
     * @param Request $request
     * @return ResponseModel
     */
    public function beforeResponse(string $functionName, ResponseModel $responseModel, Request $request): ResponseModel
    {
        return $responseModel;
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

            if (array_key_exists('file', $request->toArray())) {
                //TODO add relation on top if here and create a tree flatter array in array helper
                //return (new ExcelHelper())->setOptions([
                //    'store_format' => $request->get('file') == null ? 'xls' : $request->get('file'),
                //    'download_format' => $request->get('file') == null ? 'xls' : $request->get('file'),
                //])->table($data->get()->toArray())->createExcelFile()->download();
            }
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


            // return response
            $response->setData(collect($data->paginate($pageSize)));
            $response->setMessage($this->getTrans(__FUNCTION__, 'successful'));

        } catch (QueryException $exception) {

            // return response
            $response->setData(collect($exception->getMessage()));
            $response->setError(['query_exception' => $exception->getMessage()]);
            $response->setMessage($this->getTrans(__FUNCTION__, 'failed'));
        }
        return SmartResponse::response($response);
    }

    /**
     * @param Request $request
     * @param $validationArray
     * @return \Illuminate\Support\MessageBag|null
     */
    public function checkRequestValidation(Request $request, $validationArray): ?MessageBag
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
    public function create():JsonResponse
    {
        // Create Response Model
        $response = new ResponseModel();

        // return response
        $response->setData(collect($this->model->getFillable()));
        $response->setMessage($this->getTrans('create', 'successful'));
        return SmartResponse::response($response);
    }

    /**
     * Be careful this is recursive function
     * @param Request $request
     * @return JsonResponse
     */
    public function store(Request $request):JsonResponse
    {
        $response = new ResponseModel();

        if (!isset($userId)) {
            $userId = Auth::id();
        }

        //add author id into the request if doesn't exist
        if (is_null($request->get('author_id'))) {
            $request['author_id'] = $userId;
        }

        //add user id into the request if doesn't exist
        if (is_null($request->get('user_id'))) {
            $request['user_id'] = $userId;
        }

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

        try {
            [$model, $resultObject] = $this->deepAssociation($this->model, $objects);
            $response->setData(collect($resultObject));
            $response->setMessage($this->getTrans(__FUNCTION__, 'success'));
            $response->setStatusCode(201);
        } catch (\Exception $exception) {
            $response->setError([$exception->getMessage()]);
            $response->setMessage($this->getTrans(__FUNCTION__, 'failed'));
            $response->setStatusCode(409);
        }

        // Assign something before response
        $response = $this->beforeResponse(__FUNCTION__, $response, $request);

        // Response
        return SmartResponse::response($response);
    }

    /**
     * @param array $arr
     * @return bool
     */
    function isAssoc(array $arr):bool
    {
        if (array() === $arr) return false;
        return array_keys($arr) !== range(0, count($arr) - 1);
    }

    /**
     * @param $model
     * @param array $objects
     * @return array
     */
    public function deepAssociation($model, array $objects):array
    {
        $modelKeys = $model->getModel()->getKeyName();
        if ($this->isAssoc($objects)) {
            [$model, $resultObject] = $this->createObjectsInDB($model, $modelKeys, $objects);
        } else {
            $resultObject = [];
            foreach ($objects as $object) {
                [$tmp, $resultObjectItem] = $this->createObjectsInDB($model, $modelKeys, $object);
                array_push($resultObject, $resultObjectItem);
            }

        }
        return [$model, $resultObject];
    }

    /**
     * @param $model
     * @param $modelKeys
     * @param array $object
     * @return mixed
     */
    private function firstOrCreateObject($model, $modelKeys, array $object)
    {
        list($condition, $object) = $this->getConditionByModelKeys($modelKeys, $object);

        if (
            is_array($condition) &&
            count($condition)
        ) {
            $currentModelObject = $model->getModel()->where($condition)->first();

            if ($currentModelObject == null) {
                $modelObject = $model->firstOrCreate($condition, $object);
                $modelObject->update($object);
            } else {
                $currentModelObject->update($object);
                $modelObject = $currentModelObject;
                if ($model instanceof HasMany) {
                    $model->save($currentModelObject);
                } else {
                    if (count($model->toArray()) > 0) {
                        $model->save($currentModelObject);
                    }
                }
            }
        } else {
            $modelObject = $model->create($object);
        }
        return $modelObject;
    }

    /**
     * @param $object
     * @param $modelObject
     * @return array
     */
    private function findAnotherObjectForAssociation($object, $modelObject):array
    {
        $model = null;
        $resultObject = $modelObject->toArray();
        foreach ($object as $objectKey => $objectValue) {
            if (is_array($objectValue) && !key_exists($objectKey, $modelObject->getFillable())) {
                [$model, $resultObjectItems] = $this->deepAssociation($modelObject->$objectKey(), $objectValue);
                $resultObject[$objectKey] = $resultObjectItems;
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
    public function show(Request $request):JsonResponse
    {
        $filters = [];
//        // handle permission
//        $filters = $this->handlePermission(__FUNCTION__);

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
    public function edit(Request $request):JsonResponse
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
     */
    public function update(Request $request):JsonResponse
    {
        // Handle permission
//        $request = $this->handlePermission(__FUNCTION__,$request);
//        if ($request->has('permission_filters')){
//            $filters = $request['permission_filters'];
//        }else{
        $filters = [];
//        }

        // Create Response Model
        $response = new ResponseModel();

        $queryStringKey = $this->modelKey ? $this->modelKey : Str::singular(strtolower($this->model->getTable()));
        $index = $request->$queryStringKey;

        // Filters
        array_push($filters,
            [$this->model->getKeyName(), '=', $index]
        );

        $validateParams = $this->getArrayWithPriority($this->updateValidateArray, $this->storeValidateArray);
        $validationErrors = $this->checkRequestValidation($request, $validateParams);
        if ($validationErrors != null) {
            $response->setStatusCode(422);
            $response->setMessage($this->getTrans(__FUNCTION__, 'validation_failed'));
            $response->setError($validationErrors->toArray());
            return SmartResponse::response($response);
        }

        try {
            // sync many to many relation
//            foreach ($this->pivotFields as $pivotField) {
//                if (collect($request[$pivotField])->count()) {
//                    $pivotMethod = (new StringHelper())->toCamel($pivotField);
//                    dd($this->model->findOrFail($id)->tags()->detach());
//                    $this->model->findOrFail($id)->$pivotMethod()->sync(json_decode($request[$pivotField], true));
//                }
//            }
            foreach ($this->pivotFields as $pivotField) {
                if (collect($request[$pivotField])->count()) {
                    $pivotField = (new StringHelper())->toCamel($pivotField);
                    $this->model->find($id)->$pivotField()->sync(json_decode($request[$pivotField]));
                }
            }


            //get result of update
            $result = $this->model->where($filters)->firstOrFail()->update($request->all());

            // return response
            $response->setData($this->model
                ->where($filters)
                ->with(collect($this->updateLoad)->count() == 0 ? $this->indexRelations : $this->updateLoad)
                ->get());
            $response->setMessage(
                $this->getTrans(__FUNCTION__, 'successful1') .
                $result .
                $this->getTrans(__FUNCTION__, 'successful2')
            );

        } catch (ModelNotFoundException $exception) {
            $response->setStatusCode(404);
            $response->setMessage($this->getTrans(__FUNCTION__, 'model_not_found'));
            $response->setError(['model_not_found_exception' => $exception->getMessage()]);
            if (env('APP_DEBUG', false)) {
                $response->setData(collect($exception->getMessage()));
            }

        } catch (QueryException $exception) {
            $response->setMessage($this->getTrans(__FUNCTION__, 'failed'));
            $response->setError(['query_exception' => $exception->getMessage()]);
        }

        return SmartResponse::response($response);
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function destroy(Request $request):JsonResponse
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
     * @return string|null
     */
    public function getTrans($method, $status): ?string
    {
        if (is_null($this->defaultLocaleClass)) {
            $className = Arr::last(explode('\\', get_class($this)));
        } else {
            $className = $this->defaultLocaleClass;
        }
        $message = trans(
            'laravel-smart-restful::' .
            $this->localPrefix . '.' .
            ($className === '' ? '' : ($className . '.')) .
            $method . '.' .
            $status
        );
        if (is_string($message)) {
            return $message;
        }
        return null;
    }


    /**
     * set locale
     */
    public function setLocale():void
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
     * @param Request $request
     * @param $id
     * @return array
     */
    public function handleStoreBranchPermission(Request $request, $id)
    {
        // check for group of user
        if (!is_null($this->groupTitle)) {
            // get group model
            $group = new Group();
            $group = $group->where('title', $this->groupTitle)->first();
            $request['group_id'] = $group['id'];
        }

        $request['owner_id'] = auth()->id();

        return [$request, null];
    }

    /**
     * @param Request $request
     * @param $id
     * @return array
     */
    public function handleStoreOwnPermission(Request $request, $id)
    {
        // check for group of user
        if (!is_null($this->groupTitle)) {
            // get group model
            $group = new Group();
            $group = $group->where('title', $this->groupTitle)->first();
            $request['group_id'] = $group['id'];
        }

        $request['owner_id'] = auth()->id();

        return [$request, null];
    }

    /**
     * @param Request $request
     * @param $id
     * @return array
     */
    public function handleStoreGuestPermission(Request $request, $id)
    {
        $request['owner_id'] = null;

        return [$request, null];
    }

    /**
     * @param Request $request
     * @param $id
     * @return array
     */
    public function handleShowAdminPermission(Request $request, $id)
    {
        $filters = [];
        return [$request, $filters];
    }

    /**
     * @param Request $request
     * @param $id
     * @return array
     */
    public function handleShowBranchPermission(Request $request, $id)
    {
        $filters = [];
        array_push($filters,
            ["owner_id", "=", auth()->id()]
        );
        if (!is_null($this->groupTitle)) {

            // get group model
            $group = new Group();
            $group = $group->where('title', $this->groupTitle)->first();

            // put group_id filter
            array_push($filters,
                ['group_id', '=', $group['id']]);
        }
        return [$request, $filters];
    }

    /**
     * @param Request $request
     * @param $id
     * @return array
     */
    public function handleShowOwnPermission(Request $request, $id)
    {
        $filters = [];
        array_push($filters,
            ["owner_id", "=", auth()->id()]
        );

        // check for group of user
        if (!is_null($this->groupTitle)) {

            // get group model
            $group = new Group();
            $group = $group->where('title', $this->groupTitle)->first();

            // put group_id filter
            array_push($filters,
                ['group_id', '=', $group['id']]);
        }

        return [$request, $filters];
    }

    /**
     * @param Request $request
     * @param $id
     * @return array
     */
    public function handleShowGuestPermission(Request $request, $id)
    {
        $filters = [];
        array_push($filters,
            ["owner_id", "=", auth()->id()]
        );

        // check for group of user
        if (!is_null($this->groupTitle)) {

            // get group model
            $group = new Group();
            $group = $group->where('title', $this->groupTitle)->first();

            // put group_id filter
            array_push($filters,
                ['group_id', '=', $group['id']]);
        }

        return [$request, $filters];
    }

    public function handleEditAdminPermission(Request $request, $id)
    {
        $filters = [];
        return [$request, $filters];
    }

    public function handleEditBranchPermission(Request $request, $id)
    {
        $filters = [];
        array_push(
            $filters,
            ["owner_id", "=", auth()->id()]
        );
        if (!is_null($this->groupTitle)) {
            $group = new Group();
            $group = $group->where('title', $this->groupTitle)->first();
            array_push($filters,
                ['group_id', '=', $group['id']]);
        }
        return [$request, $filters];
    }

    /**
     * @param $functionName
     * @param Request|null $request
     * @param $params
     * @return array|Request
     */
    public function handleOwnPermission($functionName, Request $request = null, $params)
    {
        switch ($functionName) {
            case 'index':
            case 'store':
            case 'show':
            case 'edit':
                $filters = [];
                array_push($filters,
                    ["owner_id", "=", auth()->id()]
                );

                // check gor group of user
                if (!is_null($this->groupTitle)) {
                    // get group model
                    $group = new Group();
                    $group = $group->where('title', $this->groupTitle)->first();

                    // put group_id filter
                    array_push($filters,
                        ['group_id', '=', $group['id']]);
                }

                return $filters;
            case 'create':
            case 'update':

                $filters = [];
                // put to filter
                array_push($filters,
                    ["owner_id", "=", auth()->id()]
                );

                // put to request
                $request['owner_id'] = auth()->id();

                // check for group of user
                if (!is_null($this->groupTitle)) {
                    // get group model
                    $group = new Group();
                    $group = $group->where('title', $this->groupTitle)->first();

                    // put group_id filter
                    array_push($filters,
                        ['group_id', '=', $group['id']]);

                    // put to request
                    $request['group_id'] = $group['id'];
                }

                $request['permission_filters'] = $filters;
                return $request;
            case 'destroy':
                $filters = [];
                array_push($filters,
                    ["owner_id", "=", auth()->id()],
                    ["group_id", "=", $this->groupTitle]
                );
                return $filters;
            default:
                return $params;
        }
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

    public function getRequestTagName($name): String
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
    public function setModel(Model $model)
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
    }

    /**
     * @param Request $request
     * @return mixed|string
     */
    public function getOrderBy(Request $request)
    {
        $orderBy = $request->get('order_by');
        if (is_null($orderBy) || $orderBy == "") {
            if (is_array($this->model->getKeyName())) {
//                dd($this->model->getKeyName()[0]);
                $orderBy = "[\"" . $this->model->getKeyName()[0] . "\",\"DESC\"]";
            } else {
                $orderBy = "[\"" . $this->model->getKeyName() . "\",\"DESC\"]";
            }
        }
//        dd($orderBy);
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
        return $data->with($relations);
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
}