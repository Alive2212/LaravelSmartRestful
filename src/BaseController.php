<?php

namespace Alive2212\LaravelSmartRestful;

use Alive2212\ExcelHelper\ExcelHelper;
use Alive2212\LaravelQueryHelper\QueryHelper;
use Alive2212\LaravelRequestHelper\RequestHelper;
use Alive2212\LaravelSmartResponse\ResponseModel;
use Alive2212\LaravelSmartResponse\SmartResponse;
use Alive2212\LaravelStringHelper\StringHelper;
use App;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\MessageBag;
use Illuminate\Support\Facades\Validator;



class BaseController extends Controller
{
    /**
     * to use this class
     * create message list as messages in message file
     * override __constructor and define your model
     * define your rules for index,store and update
     */

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
    protected $indexLoad = [
        //
    ];

    /**
     * array of relationship for eager loading
     *
     * @var array
     */
    protected $editLoad = [
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
     * defaultController constructor.
     * @param Model $model
     * @param array $middleware
     */
    public function __construct(Model $model, $middleware = [])
    {
        $this->modelName = (new StringHelper)->singular(strtolower($model->getTable()));
        $this->model = $model;
        array_push($middleware, 'restful');//add restful middleware
        $this->middleware($middleware);
    }

    /**
     * Display a listing of the resource.
     *
     * @param Request $request
     * @return string
     */
    public function index(Request $request)
    {
        //set default pagination
        if (is_null($request['page']['size'])) {
            $request['page'] = ['size' => config('json-api-paginate.default_results_per_page')];
        }

        //set default ordering
        if (is_null($request['order_by'])) {
            $request['order_by'] = "{\"field\":\"id\",\"operator\":\"Desc\"}";
        }

        $validationErrors = $this->checkRequestValidation($request, $this->indexValidateArray);
        if ($validationErrors != null) {
            return SmartResponse::json(
                $this->message('validation_fails'),
                false,
                200,
                $validationErrors
            );
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
//                (new ExcelHelper())->setOptions([
//                    'store_format' => $request->get('file') == null ? 'xls' : $request->get('file'),
//                    'download_format' => $request->get('file') == null ? 'xls' : $request->get('file'),
//                ])->table($data->get()->toArray())->createExcelFile()->store('xls', storage_path('excel/exports'));
//                return "I am the One for hom";
                return (new ExcelHelper())->setOptions([
                    'store_format' => $request->get('file') == null ? 'xls' : $request->get('file'),
                    'download_format' => $request->get('file') == null ? 'xls' : $request->get('file'),
                ])->table($data->get()->toArray())->createExcelFile()->download();
            }
            $data = $data->with($this->indexLoad);

            $data = (new QueryHelper())->deepFilter($data, (new RequestHelper())->getCollectFromJson($request['filters']));
            $data = (new QueryHelper())->orderBy($data, (new RequestHelper())->getCollectFromJson($request['order_by']));

            // TODO for beautiful response must be a module
            $message = new App\Resources\MessageHelper();
            $message->setOption('isDefault', true);
            if ($request['beautiful_response']=='true' || $request['response']=='beautiful' || $request['response']=='flat') {
                $getCurrentClassFunction = $this->modelName . '.' . __FUNCTION__;
                $responseParams = (new App\Resources\Config\ConfigHelper())->getFromResponse($getCurrentClassFunction);
                if (!is_null($responseParams)) {
                    $message->setBeautifulData(collect($data->jsonPaginate()), $responseParams);
                    $message->initMessage();
                    return SmartResponse::message($message);
                }
            }
            $message->setData($data->jsonPaginate());
            $message->initMessage();
            return SmartResponse::message($message);

            return SmartResponse::json(
                $this->message('successful'),
                true,
                200,
                $data->jsonPaginate()
            );
        } catch (QueryException $exception) {
            return SmartResponse::json(
                $this->message('failed'),
                false,
                200
            );
        }
    }

    public function checkRequestValidation(Request $request, $validationArray)
    {
        $requestParams = $request->toArray();
        $validator = Validator::make($request->all(), $validationArray);
        if ($validator->fails()) {
            return $validator->errors();
        }
        if (is_numeric(array_search($request->getMethod(), ["POST", "PUT", "PATCH"]))) {
            $errors = new MessageBag();
            foreach ($requestParams as $requestParamKey => $requestParamValue) {
                if (is_numeric(array_search($requestParamKey, $this->uniqueFields))) {
                    if ($this->checkExistUniqueRecord($requestParamKey, $requestParamValue)) {
                        $errors->add($requestParamKey, 'This ' . $requestParamKey . ' is exist try another.');
                    }
                }
            }
            if (collect($errors)->count() > 0) {
                return $errors;
            }
        }
        return null;
    }

    /**
     * @param $key
     * @param $value
     * @return bool
     */
    public function checkExistUniqueRecord($key, $value)
    {
        if ($this->model->where($key, $value)->count()) {
            return true;
        }
        return false;
    }

    /**
     * @param $status
     * @return mixed
     */
    public function message($status)
    {
        $key = $this->messagePrefix . $this->modelName . '.' . debug_backtrace()[1]['function'] . '.' . $status;
        return $this->getMessageFromFile($key);
    }

    /**
     * @param $key
     * @return mixed
     */
    public function getMessageFromFile($key)
    {
        return config($key);
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        return SmartResponse::json(
            $this->message('successful'),
            true,
            200,
            $this->model->getFillable()
        );
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        $response = new ResponseModel();
        // TODO must set access in middle ware
        //get user id
        $userId = auth()->id();

        //add author id into the request if doesn't exist
        if(isset($request['author_id'])){
            if (is_null($request['author_id'])){
                $request['author_id'] = $userId;
            }
        }else{
            $request['author_id'] = $userId;
        }

        //add user id into the request if doesn't exist
        if(isset($request['user_id'])){
            if (is_null($request['user_id'])){
                $request['user_id'] = $userId;
            }
        }else{
            $request['user_id'] = $userId;
        }

        $validationErrors = $this->checkRequestValidation($request, $this->storeValidateArray);
        if ($validationErrors != null) {
            if (env('APP_DEBUG',false)){
                $response->setMessage(json_encode($validationErrors->getMessages()));
            }
            $response->setStatus(false);
            return SmartResponse::response($response);
        }
        try {
            // get result of model creation
            $result = $this->model->create($request->all());
            // sync many to many relation
            foreach ($this->pivotFields as $pivotField) {
                if (collect($request[$pivotField])->count()) {
                    $pivotField = (new StringHelper())->toCamel($pivotField);
                    $this->model->find($result['id'])->$pivotField()->sync(json_decode($request[$pivotField]));
                }
            }
            $response->setMessage('successful');
            $response->setData(collect($result->toArray()));
            $response->setStatus(true);
            return SmartResponse::response($response);
        } catch (QueryException $exception) {
            if (env('APP_DEBUG',false)){
                $response->setMessage($exception->getMessage());
            }
            $response->setStatus(false);
            return SmartResponse::response($response);
        }
    }

    /**
     * Display the specdefaultied resource.
     *
     * @param  int $id
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        try {
            return SmartResponse::json(
                $this->message('successful'),
                true,
                200,
                $this->model->findOrFail($id)
            );
        } catch (ModelNotFoundException $exception) {
            return SmartResponse::json(
                $this->message('failed'),
                false,
                200,
                $exception->getMessage()
            );
        }
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int $id
     * @return \Illuminate\Http\Response
     */
    public function edit($id)
    {
        try {
            return SmartResponse::json(
                $this->message('successful'),
                true,
                200,
                $this->model
                    ->where($this->model->getKeyName(), $id)
                    ->with(collect($this->editLoad)->count() == 0 ? $this->indexLoad: $this->editLoad)
                    ->get()
            );
        } catch (ModelNotFoundException $exception) {
            return SmartResponse::json(
                $this->message('failed'),
                false,
                200,
                $exception->getMessage()
            );
        }
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request $request
     * @param  int $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id)
    {
        $validationErrors = $this->checkRequestValidation($request, $this->updateValidateArray);
        if ($validationErrors != null) {
            return SmartResponse::json(
                $this->message('validation fails'),
                false,
                200,
                $validationErrors
            );
        }
        try {
            // sync many to many relation
            foreach ($this->pivotFields as $pivotField) {
                if (collect($request[$pivotField])->count()) {
                    $pivotMethod = (new StringHelper())->toCamel($pivotField);
                    $this->model->findOrFail($id)->$pivotMethod()->sync(json_decode($request[$pivotField],true));
                }
            }
            //get result of update
            $result = $this->model->findOrFail($id)->update($request->all());
            return SmartResponse::json(
                $this->message('successful'),
                $result,
                200,
                env('APP_DEBUG') ? $this->model->find($id) : []
            );
        } catch (ModelNotFoundException $exception) {
            return SmartResponse::json(
                $this->message('failed'),
                false,
                200,
                $exception->getMessage()
            );
        } catch (QueryException $exception) {
            return SmartResponse::json(
                $this->message('failed'),
                false,
                200,
                $exception->getMessage()
            );
        }
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        try {
            $this->model->findOrFail($id)->delete();
            return SmartResponse::json(
                $this->message('successful'),
                true,
                200
            );
        } catch (ModelNotFoundException $exception) {
            return SmartResponse::json(
                $this->message('failed'),
                false,
                200,
                $exception->getMessage()
            );
        }
    }
}