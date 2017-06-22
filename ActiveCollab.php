<?php
/**
 * @author Самсонов Владимир <samsonov.sem@gmail.com>
 * @copyright Copyright &copy; S.E.M. 2017-
 * @license http://www.opensource.org/licenses/bsd-license.php New BSD License
 */

namespace sem\activecollab;

use Yii;
use yii\base\Component;
use yii\helpers\Json;
use ActiveCollab\SDK\Authenticator\SelfHosted;
use ActiveCollab\SDK\TokenInterface;
use ActiveCollab\SDK\Client;
use ActiveCollab\SDK\Exceptions\AppException;

/**
 * Компонент взаимодействия с API ActiveCollab
 * 
 * @see https://developers.activecollab.com
 * @see https://github.com/activecollab/activecollab-feather-sdk
 * 
 * @property-read \ActiveCollab\SDK\Authenticator\SelfHosted|NULL $authenticator [[getAuthenticator()]]
 * @property-read \ActiveCollab\SDK\TokenInterface|false $token [[getToken()]]
 * @property-read \ActiveCollab\SDK\Client|flase $client [[getClient()]]
 * @property-read array $requestErrors [[getRequestErrors()]]
 */
class ActiveCollab extends Component
{
    
    /**
     * Типа HTTP-запроса GET
     */
    const REQUEST_TYPE_GET = 'get';

    /**
     * Типа HTTP-запроса POST
     */    
    const REQUEST_TYPE_POST = 'post';
    
    /**
     * Типа HTTP-запроса HEAD
     */
    const REQUEST_TYPE_HEAD = 'head';
    
    /**
     * Типа HTTP-запроса PUT
     */
    const REQUEST_TYPE_PUT = 'put';
    
    /**
     * Типа HTTP-запроса PATCH
     */
    const REQUEST_TYPE_PATCH = 'patch';
    
    /**
     * Типа HTTP-запроса DELETE
     */
    const REQUEST_TYPE_DELETE = 'delete';
    
    const ERROR_CLIENT_NOT_READY = 100;
    
    const ERROR_TOKEN_UNDEFINED = 99;
    
    /**
     * Наименование компании
     * @var string
     */
    public $companyName;
    
    /**
     * Наименование приложения
     * @var string
     */
    public $applicationName;
    
    /**
     * Имя поользователя (email), для которого выполняется вход для работы с API
     * @var string
     */
    public $user;
    
    /**
     * Пароль
     * @var string
     */
    public $password;
    
    /**
     * URL-адрес self-hosted сервиса AC
     * @var string
     */
    public $acUrl;
    
    /**
     * Версия API
     * @var number
     */
    public $apiVersion = 5;
    
    /**
     * Необходима ли SSL-проверка
     * @var bool
     */
    public $sslVerify = false;
    
    /**
     * Объект аутентификации для получения реквизитов доступа пользователя
     * @var \ActiveCollab\SDK\Authenticator\SelfHosted|NULL
     */
    protected $_authenticator;
    
    /**
     * Объект токена авторизации пользовтаеля
     * @var \ActiveCollab\SDK\TokenInterface|NULL|false
     */
    protected $_token;
    
    /**
     * Объект клиента для обмена данными с AC
     * @var \ActiveCollab\SDK\Client|NULL|false
     */
    protected $_client;
    
    /**
     * Ошибки последнего запроса
     * @var array
     */
    protected $_requestErrors = [];


    /**
     * @return \ActiveCollab\SDK\Authenticator\SelfHosted
     */
    protected function getAuthenticator()
    {
	if (is_null($this->_authenticator)) {
	    $this->_authenticator = new SelfHosted($this->companyName, $this->applicationName, $this->user, $this->password, $this->acUrl, $this->apiVersion);
	    $this->_authenticator->setSslVerifyPeer($this->sslVerify);
	}
	
	return $this->_authenticator;
    }
    
    /**
     * @return \ActiveCollab\SDK\TokenInterface|false
     */
    protected function getToken()
    {
	if (is_null($this->_token)) {
	    $this->_token = $this->authenticator->issueToken();
	}
	
	if ($this->_token instanceof TokenInterface) {
	    return $this->_token;
	}
	
	$this->addRequestError(self::ERROR_TOKEN_UNDEFINED);
	return false;
    }
    
    /**
     * @return \ActiveCollab\SDK\Client|false
     */
    protected function getClient()
    {
	if (is_null($this->_client)) {
	    
	    if ($this->token !== false) {
		$this->_client = new Client($this->token);
		$this->_client->setSslVerifyPeer($this->sslVerify);
	    } else {
		$this->addRequestError(self::ERROR_CLIENT_NOT_READY);
		$this->_client = false;
	    }
	    
	}
	
	return $this->_client;
    }
    
    /**
     * Производит запрос к API-сервису и выводит результат в виде массива
     * 
     * @param string $path story path @see https://developers.activecollab.com/api-documentation/
     * @param array $params параметры запроса
     * @param string $type тип запроса
     * @return array|false массив с данными или false в случае неготовности клиента
     */
    public function request($path, $params = [], $type = self::REQUEST_TYPE_GET)
    {
	$this->flushRequestErrors();
	
	if (!in_array($type, self::getHttpTypes())) {
	    throw new \yii\base\InvalidCallException("Неверный тип HTTP-запроса!");
	}
	
	try {
	    
	    if ($this->client === false) {
		return [];
	    }
	    
	    $result = $this->client->$type($path, $params)->getJson();
	    
	    if (isset($result['code']) && $result['code'] == 0) {
		$this->addRequestError(AppException::INVALID_PROPERTIES);
		return [];
	    } else {
		return $result;
	    }
	    
	} catch (AppException $exc) {
	    $this->addRequestError($exc->getHttpCode());
	    return [];
	}
    }
    
    /**
     * Производит сброс ошибок запроса перед.
     * Вызывается перед непосредственным запросом к API
     */
    protected function flushRequestErrors()
    {
	$this->_requestErrors = [];
    }

    /**
     * Производит добавление ошибки к списку ошибок запроса
     * @param integer $code
     */
    protected function addRequestError($code)
    {
	$this->_requestErrors[$code] = self::getErrorMessage($code);
    }
    
    /**
     * Возвращает список ошибок (если они были) в виде массива с описанием
     * @return array
     */
    public function getRequestErrors()
    {
	return $this->_requestErrors;
    }
    
    /**
     * Возвращает сообщение для ошибки с указанным кодом
     * 
     * @param integer $code код ошибки
     * @return string
     */
    public static function getErrorMessage($code)
    {
	$errors = self::getErrorsDescription();
	return isset($errors[$code]) ? $errors[$code] : $errors[AppException::UNAVAILABLE];
    }
    
    /**
     * Возвращает перечень возможных ошибок и их описание
     * @return array
     */
    protected static function getErrorsDescription()
    {
	return [
	    self::ERROR_TOKEN_UNDEFINED		=> 'Токен доступа не определен',
	    self::ERROR_CLIENT_NOT_READY	=> 'Клиент не готов',
	    AppException::BAD_REQUEST		=> 'Неверный HTTP-запрос к API',
	    AppException::INVALID_PROPERTIES	=> 'Неверные параметры HTTP-запроса к API',
	    AppException::UNAUTHORIZED		=> 'Сессия токена истекла или клиент не авторизован',
	    AppException::FORBIDDEN		=> 'Дейстиве или метод API запрещен',
	    AppException::NOT_FOUND		=> 'Дейстиве или метод API не найден',
	    AppException::CONFLICT		=> 'Возник конфликт при обращении к API',
	    AppException::OPERATION_FAILED	=> 'Действие или метод API временно не доступно',
	    AppException::UNAVAILABLE		=> 'Сервис API временно не доступен',
	];
    }
    
    /**
     * Возвращает список возможных HTTP-запросов
     * @return array
     */
    protected static function getHttpTypes()
    {
	return [
	    self::REQUEST_TYPE_DELETE,
	    self::REQUEST_TYPE_GET,
	    self::REQUEST_TYPE_HEAD,
	    self::REQUEST_TYPE_PATCH,
	    self::REQUEST_TYPE_POST,
	    self::REQUEST_TYPE_PUT
	];
    }
}
