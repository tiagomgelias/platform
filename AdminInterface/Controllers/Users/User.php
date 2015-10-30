<?php
namespace Selenia\Plugins\AdminInterface\Controllers\Users;
use PhpKit\WebConsole\WebConsole;
use Selenia\DataObject;
use Selenia\Exceptions\FatalException;
use Selenia\Exceptions\Flash\ValidationException;
use Selenia\Exceptions\HttpException;
use Selenia\Interfaces\UserInterface;
use Selenia\Plugins\AdminInterface\Config\AdminInterfaceModule;
use Selenia\Plugins\AdminInterface\Controllers\AdminController;
use Selenia\Plugins\AdminInterface\Models\User as UserModel;

class User extends AdminController
{
  /** Password to display when modifying an existing user. */
  const DUMMY_PASS = 'dummy password';

  protected $pageTitle = '$ADMIN_ADMIN_USER';

  public function action_delete ($param = null)
  {
    parent::action_delete ($param);
    /** @var UserInterface $data */
    $data = $this->model;
    if ($data->id () == $this->session->user ()->id()) {
      $this->session->logout();
      return $this->redirection->home();
    }
  }

  public function action_submit ($param = null)
  {
    /** @var UserModel $data */
    $data = $this->model;
    $fields = $this->request->getParsedBody();
    $settings = AdminInterfaceModule::settings ();

    $username = get ($fields, '_username');
    $password = get ($fields, '_password');

    // If the user active checkbox is not shown, $active is always true.

    $isSelf     = $data->id () == $this->session->user ()->id ();
    $showActive = !$isSelf && $settings->getActiveUsers();
    $active     = get ($fields, '_active', !$showActive);

    $role = get ($fields, '_role');

    /** @var $data UserInterface|DataObject */
    if (!isset($data))
      throw new FatalException('Can\'t insert/update NULL DataObject.');

    if ($username == '')
      throw new ValidationException(ValidationException::REQUIRED_FIELD, '$LOGIN_USERNAME');

    if ($password == self::DUMMY_PASS) $password = '';

    if ($password == '') {
      if ($data->isNew ())
        throw new ValidationException(ValidationException::REQUIRED_FIELD, '$LOGIN_PASSWORD');
      // Do not change the password if the user already exists and the password field was not modified (or left empty)
      // on the form.
    }
    else $data->password ($password);

    if ($username != $data->username ()) {
      if ($data->findByName ($username))
        throw new ValidationException(ValidationException::DUPLICATE_RECORD, '$LOGIN_USERNAME');
    }
    $data->username ($username);
    $data->active ($active);
    if (isset($role))
      $data->role ($role);

    if ($data->isNew ())
      $this->insertData ();
    else $this->updateData ();
  }

  protected function initialize ()
  {
    if (!$this->session->user ())
      throw new HttpException(403);
    parent::initialize ();
  }

  protected function model ()
  {
    $settings = AdminInterfaceModule::settings ();

    /** @var UserModel $user */
    if (get ($this->activeRoute->config ?: [], 'self')) {
      $user = $this->session->user ();
      $user->read ();
    }
    else {
      $user = $this->loadRequested (new $this->app->userModel);
      if (!$user) {
        _log ('<#section|User>', $user, '</#section>');
        WebConsole::throwErrorWithLog (new FatalException("Cant't find the user."));
      }
    }
    // Set a default role for a new user.
    if (!exists ($user->role ()))
      $user->role ($settings->getDefaultRole());
    return $user;
  }

  protected function viewModel ()
  {
    $settings = AdminInterfaceModule::settings ();

    /** @var UserInterface|DataObject $user */
    $user    = $this->model;
    $isDev   = $this->session->user ()->role () == UserInterface::USER_ROLE_DEVELOPER;
    $isAdmin = $this->session->user ()->role () == UserInterface::USER_ROLE_ADMIN;
    // Has it the Standard or Admin roles?
    $isStandard = $isAdmin || $this->session->user ()->role () == UserInterface::USER_ROLE_STANDARD;
    $isSelf     = $user->id () == $this->session->user ()->id ();

    $viewModel = [
      '_username'       => $user->username (),
      '_password'       => strlen ($user->password ()) ? self::DUMMY_PASS : '',
      '_active'         => $user->active (),
      '_role'           => $user->role (),
      'isAdmin'         => $isAdmin,
      'isNotAdminOrDev' => !$isAdmin && !$isDev,
      'isDev'           => $isDev,
      'isNotDev'        => !$isDev,
      'isStandard'      => $isStandard,
      'showRoles'       => $isDev || ($isAdmin && $settings->getEditRoles()),
      'dev_role'        => UserInterface::USER_ROLE_DEVELOPER,
      'admin_role'      => UserInterface::USER_ROLE_ADMIN,
      'standard_role'   => UserInterface::USER_ROLE_STANDARD,
      'guest_role'      => UserInterface::USER_ROLE_GUEST,
      'showActive'      => !$isSelf && $settings->getActiveUsers(),
      'canDelete'       => // Will be either true or null.
        (
          !$user->isNew () &&
          // User is not self or delete self is allowed.
          (!$isSelf || $settings->getAllowDeleteSelf())
        ) ?: null,
    ];
    return [
      'login' => $viewModel
    ];
  }

}