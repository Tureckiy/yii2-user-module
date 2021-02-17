<?php

namespace frontend\modules\user\controllers;

use common\commands\SendEmailCommand;
use common\models\User;
use common\models\UserChannels;
use common\models\UserToken;

use frontend\controllers\BaseController;
use frontend\modules\user\models\ProfileForm;
use frontend\modules\user\models\LoginForm;

use frontend\modules\user\models\PasswordResetRequestForm;
use frontend\modules\user\models\RegisterForm;
use frontend\modules\user\models\ResetPasswordForm;
use frontend\modules\user\models\SignupForm;

use frontend\models\UploadForm;
//use yii\web\UploadedFile;

use Intervention\Image\ImageManagerStatic;


use Yii;
use yii\base\Exception;
use yii\base\InvalidParamException;
use yii\filters\AccessControl;
use yii\filters\VerbFilter;
use yii\helpers\ArrayHelper;
use yii\web\BadRequestHttpException;
use yii\web\Response;
use yii\widgets\ActiveForm;
use yii\helpers\Url;

use yii\web\NotFoundHttpException;
use yii\web\ForbiddenHttpException;

/**
 * Class SignInController
 *
 * @package frontend\modules\user\controllers
 */
class SignInController extends BaseController
{
    public $defaultAction = 'login';

    /**
     * @return array
     */
    public function actions()
    {
        return [
            'oauth' => [
                'class' => 'yii\authclient\AuthAction',
                'successCallback' => [$this, 'successOAuthCallback'],
            ],
//            'avatar-upload' => [
//                'class'        => UploadAction::className(),
//                'deleteRoute'  => 'avatar-delete',
//                'on afterSave' => function ($event){
//                    /* @var $file \League\Flysystem\File */
//                    $file = $event->file;
//                    $img = ImageManagerStatic::make($file->read())
//                        ->fit(215, 215);
//                    $file->put($img->encode());
//                },
//            ],
//            'avatar-delete' => [
//                'class' => DeleteAction::className(),
//            ],
        ];
    }

    public function behaviors()
    {
        return [
            'access' => [
                'class' => AccessControl::className(),
                'rules' => [
                    //...
                    [
                        'actions' => ['logout', 'profile', 'avatar-upload', 'avatar-delete', 'notifications'],
                        'allow' => true,
                        'roles' => ['@'],
                    ],
                    // Запрещено неавторизованным
                    [
                        'allow' => false,
                        'roles' => ['@'],
                        'denyCallback' => function () {
                            $url = Url::to(['/user/sign-in/login']);
                            return Yii::$app->controller->redirect($url);
                        },
                    ],
                ],
            ],
            'verbs' => [
                'class' => VerbFilter::className(),
                'actions' => [
                    'logout' => ['get'],
                    'avatar-upload' => ['post'],
                    'avatar-delete' => ['post'],
                ],
            ],
        ];
    }

    /**
     * @param \yii\base\Action $action
     *
     * @return bool
     */
    public function beforeAction($action)
    {
        //check Autologin functionality
        $this->checkActionLoginsystemauto($action);

        try {

            return parent::beforeAction($action);
        } catch (BadRequestHttpException $tryError) {
            $this->goHome();

            return true;
        }
    }


    /**
     * @return Response
     * @throws NotFoundHttpException
     */
    public function actionLoginsystemauto()
    {

        $model = new LoginForm();
        if ($model->autologin()) {
            return $this->redirect(['/']);
        } else {
            // log
            throw new NotFoundHttpException('The requested page does not exist.');
        }

    }


    /**
     * @return array|string|Response
     */
    public function actionLogin()
    {
        $model = new LoginForm();
        if (Yii::$app->request->isAjax) {
            $model->load($_POST);
            Yii::$app->response->format = Response::FORMAT_JSON;

            return ActiveForm::validate($model);
        }

        $post = Yii::$app->request->post();

        if (($identityStr = Yii::$app->request->get('enter', false)) && !count($post)) {
            //set data for autologin
            $post['LoginForm']['identity'] = $identityStr;
            $post['LoginForm']['password'] = $identityStr;
        }

        if ($model->load($post) && $model->login()) {
            return $this->redirect(['/']);
        } else {
            $isActiveTelegram = (env('TG_BOT_API_KEY') && env('TG_BOT_LOGIN_CALLBACK'));


            return $this->render('login', [
                'model' => $model,
                'isActiveTelegram' => $isActiveTelegram,
                'formTitle' => 'Войти на онлайн Платформу',
            ]);
        }
    }


    /**
     * @return array|string|Response
     */
    public function actionLoginSocial()
    {

        $model = new LoginForm();
        if (Yii::$app->request->isAjax) {
            $model->load($_POST);
            Yii::$app->response->format = Response::FORMAT_JSON;

            return ActiveForm::validate($model);
        }
        if ($model->load(Yii::$app->request->post()) && $model->login()) {
            return $this->redirect(['/']);
        } else {
            $isActiveTelegram = (env('TG_BOT_API_KEY') && env('TG_BOT_LOGIN_CALLBACK'));

            return $this->render('login_social', [
                'model' => $model,
                'isActiveTelegram' => $isActiveTelegram,
            ]);
        }
    }


    /**
     * Register new user for testing level
     *
     * @return string|Response
     */
    public function actionGetFirstTest()
    {

        $model = new RegisterForm();
        $model->setScenario(RegisterForm::SCENARIO_FIRST_TEST);

        if ($model->load(Yii::$app->request->post())) {

            if ($model->validate()) {
                $user = $model->signup();
                if ($user) {
                    //if ($model->shouldBeActivated()) {

                    return $this->goHome();
                }

            } else {
                return $this->render('register_first_test', [
                    'model' => $model,
                ]);
            }

        }

        return $this->render('register_first_test', [
            'model' => $model,
        ]);
    }

    /**
     * @return Response
     */
    public function actionLogout()
    {

        Yii::$app->user->logout();
        $url = Url::to(['/user/sign-in/login']);
        return $this->redirect($url);
    }

    /**
     * @return string|Response
     */
    public function actionSignup()
    {

        $model = new SignupForm();
        if ($model->load(Yii::$app->request->post())) {
            $user = $model->signup();
            if ($user) {
                if ($model->shouldBeActivated()) {
                    //
                } else {
                    Yii::$app->getUser()
                        ->login($user);

                    Yii::$app->getSession()
                        ->setFlash('alert', [
                            'body' => Yii::t('frontend', 'Your account has been successfully created. Please save your profile amd go to the TARIFF page.'),
                            'options' => ['class' => 'alert-success'],
                        ]);
                }

                return $this->goHome();
                //return $this->redirect('/user/sign-in/profile');
            }
        }

        return $this->render('signup', [
            'model' => $model,
        ]);
    }

    public function actionActivation($token)
    {

        $token = UserToken::find()
            ->byType(UserToken::TYPE_ACTIVATION)
            ->byToken($token)
            ->notExpired()
            ->one();

        if (!$token) {
            throw new BadRequestHttpException;
        }

        $user = $token->user;
        $user->updateAttributes([
            'status' => User::STATUS_ACTIVE,
        ]);
        $token->delete();
        Yii::$app->getUser()
            ->login($user);
        Yii::$app->getSession()
            ->setFlash('alert', [
                'body' => Yii::t('frontend', 'Your account has been successfully activated.'),
                'options' => ['class' => 'alert-success'],
            ]);

        return $this->goHome();
    }

    /**
     * @return string|Response
     */
    public function actionRequestPasswordReset()
    {
        $model = new PasswordResetRequestForm();
        if ($model->load(Yii::$app->request->post()) && $model->validate()) {
            if ($model->sendEmail()) {
                Yii::$app->getSession()
                    ->setFlash('alert', [
                        'body' => Yii::t('frontend', 'Check your email for further instructions.'),
                        'options' => ['class' => 'alert-success'],
                    ]);

                return $this->goHome();
            } else {
                Yii::$app->getSession()
                    ->setFlash('alert', [
                        'body' => Yii::t('frontend', 'Sorry, we are unable to reset password for email provided.'),
                        'options' => ['class' => 'alert-danger'],
                    ]);
            }
        }

        return $this->render('requestPasswordResetToken', [
            'model' => $model,
        ]);
    }

    /**
     * @param $token
     *
     * @return string|Response
     * @throws BadRequestHttpException
     */
    public function actionResetPassword($token)
    {
        try {
            $model = new ResetPasswordForm($token);
        } catch (InvalidParamException $e) {
            throw new BadRequestHttpException($e->getMessage());
        }

        if ($model->load(Yii::$app->request->post()) && $model->validate() && $model->resetPassword()) {
            Yii::$app->getSession()
                ->setFlash('alert', [
                    'body' => Yii::t('frontend', 'New password was saved.'),
                    'options' => ['class' => 'alert-success'],
                ]);

            return $this->goHome();
        }

        return $this->render('resetPassword', [
            'model' => $model,
        ]);
    }

    /**
     * @param $client \yii\authclient\BaseClient
     *
     * @return bool
     * @throws Exception
     */
    public function successOAuthCallback($client)
    {

        // use BaseClient::normalizeUserAttributeMap to provide consistency for user attribute`s names
        $attributes = $client->getUserAttributes();
        $user = User::find()
            ->where([
                'oauth_client' => $client->getName(),
                'oauth_client_user_id' => ArrayHelper::getValue($attributes, 'id'),
            ])
            ->one();
        if (!$user) {
            $user = new User();
            $user->scenario = 'oauth_create';
            $user->username = ArrayHelper::getValue($attributes, 'login');
            $user->email = ArrayHelper::getValue($attributes, 'email');
            $user->oauth_client = $client->getName();
            $user->oauth_client_user_id = ArrayHelper::getValue($attributes, 'id');
            $password = Yii::$app->security->generateRandomString(8);
            $user->setPassword($password);
            if ($user->save()) {
                $profileData = [];
                if ($client->getName() === 'facebook') {
                    $profileData['firstname'] = ArrayHelper::getValue($attributes, 'first_name');
                    $profileData['lastname'] = ArrayHelper::getValue($attributes, 'last_name');
                }
                $user->afterSignup($profileData);
                $sentSuccess = Yii::$app->commandBus->handle(new SendEmailCommand([
                    'view' => 'oauth_welcome',
                    'params' => ['user' => $user, 'password' => $password],
                    'subject' => Yii::t('frontend', '{app-name} | Your login information', ['app-name' => Yii::$app->name]),
                    'to' => $user->email,
                ]));
                if ($sentSuccess) {
                    Yii::$app->session->setFlash('alert', [
                        'options' => ['class' => 'alert-success'],
                        'body' => Yii::t('frontend', 'Welcome to {app-name}. Email with your login information was sent to your email.', [
                            'app-name' => Yii::$app->name,
                        ]),
                    ]);
                }

            } else {
                // We already have a user with this email. Do what you want in such case
                if ($user->email && User::find()
                        ->where(['email' => $user->email])
                        ->count()
                ) {
                    Yii::$app->session->setFlash('alert', [
                        'options' => ['class' => 'alert-danger'],
                        'body' => Yii::t('frontend', 'We already have a user with email {email}', [
                            'email' => $user->email,
                        ]),
                    ]);
                } else {
                    Yii::$app->session->setFlash('alert', [
                        'options' => ['class' => 'alert-danger'],
                        'body' => Yii::t('frontend', 'Error while oauth process.'),
                    ]);
                }

            };
        }
        if (Yii::$app->user->login($user, 3600 * 24 * 30)) {
            return true;
        } else {
            throw new Exception('OAuth error');
        }
    }

    /**
     * @return string|Response
     * @throws Exception
     * @throws \ReflectionException
     */
    public function actionProfile()
    {
        $userProfileModel = Yii::$app->user->identity->userProfile;

        $profileForm = new ProfileForm();

        $profileForm->setScenario(ProfileForm::SCENARIO_UPDATE_PROFILE);
        $profileForm->setModel($userProfileModel);

        $userModel = Yii::$app->user->identity;
        $profileTab = Yii::$app->request->post('profileTab',
            Yii::$app->request->get('tab', 'profile'));


        if ($_FILES && $_FILES['imageFiles']['tmp_name']) {

            $modelFile = new UploadForm();
            $modelFile->setModel($userProfileModel);
            $modelFile->setScenario(UploadForm::SCENARIO_IMAGE);

            $modelFile->imageFiles = $_FILES['imageFiles'];

            if (!$modelFile->upload()) {

                Yii::$app->session->setFlash('error', Yii::t('backend', 'File Not Uploaded'));
                return $this->redirect(['/user/sign-in/profile', 'tab' => 'avatar']);
            } else {
                Yii::$app->session->setFlash('success', Yii::t('backend', 'Your avatar has been updated successfully'));
            }


        } elseif (isset($_POST['imageFilesRemove']) && 1 == $_POST['imageFilesRemove']) {
            // check if deleted

            $modelFile = new UploadForm();
            $modelFile->setModel($userProfileModel);
            $modelFile->removeImage();
        }


        if ($profileForm->load(Yii::$app->request->post()) && $profileForm->validate() && $profileForm->save(false)) {

            Yii::$app->session->setFlash('success', Yii::t('backend', 'Your profile has been successfully saved'));

            if ($profileForm->passwordField) {
                //update user [wd
                $userModel->setPassword($profileForm->passwordField);
                //and update pwd_updated
                Yii::$app->session->setFlash('success', Yii::t('frontend', 'Your Password successfully changed'));
            }

            $userModel->setProfile = 1;
            $userModel->save(false);


            return $this->redirect(['/user/sign-in/profile', 'tab' => $profileTab]);
        } else {

            if (0 == $userModel->setProfile) {
                $queryParams = Yii::$app->request->queryParams;

                if (isset($queryParams['update'])) {
                    Yii::$app->session->setFlash('warning', Yii::t('frontend', 'Пожалуйста заполните свой профиль.'));

                }
            }
        }

        if ($profileForm->hasErrors()) {
            Yii::$app->session->setFlash('danger', Yii::t('frontend', 'Please check the form and fill in the required fields.'));
        }

        return $this->render('profile', [
            'model' => $userProfileModel,
            'profileForm' => $profileForm,
            //   'userModel' => $userModel,
            'userNotificationChannels' => [],
            'activeTab' => $profileTab
        ]);
    }

    public function actionNotifications()
    {
        return $this->render('notifications', [
            'userNotificationChannels' => UserChannels::getAllUserChannels(),
        ]);
    }

    private function uploadFile($model, $files)
    {
        $model->imageFile = UploadedFile::getInstance($model, 'imageFile');

        foreach ($files as $file) {

            $fileExtension = $file->getClientOriginalExtension();
            $fileName = time() . "_" . rand(0, 9999999) . "_" . md5(rand(0, 9999999)) . "." . $fileExtension;

            $folder_name = '/upload/plate/' . date('Y/m/d') . '/';
            $link_image = $folder_name . $fileName;
            $file->move(public_path() . $folder_name, $fileName);
            $data_images = array('plate_id' => $plate_id, 'plate_image_link' => $link_image);

            //save
            Model_Plate_Images::create($data_images);
        }
    }
}
