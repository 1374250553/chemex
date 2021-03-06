<?php

namespace App\Admin\Controllers;

use App\Admin\Actions\Grid\BatchAction\UserBatchDeleteAction;
use App\Admin\Actions\Grid\RowAction\UserDeleteAction;
use App\Admin\Actions\Grid\ToolAction\UserImportAction;
use App\Admin\Grid\Displayers\RowActions;
use App\Admin\Repositories\User;
use App\Models\Department;
use App\Support\Data;
use App\Support\Support;
use Dcat\Admin\Admin;
use Dcat\Admin\Form;
use Dcat\Admin\Grid;
use Dcat\Admin\Http\Controllers\UserController as BaseUserController;
use Dcat\Admin\Layout\Content;
use Dcat\Admin\Layout\Row;
use Dcat\Admin\Show;
use Dcat\Admin\Widgets\Tab;
use Dcat\Admin\Widgets\Tree;
use Illuminate\Http\Request;

/**
 * @property int ad_tag
 */
class UserController extends BaseUserController
{
    public function index(Content $content): Content
    {
        return $content
            ->title($this->title())
            ->description(admin_trans_label('description'))
            ->body(function (Row $row) {
                $tab = new Tab();
                $tab->add(Data::icon('user') . admin_trans_label('User'), $this->grid(), true);
                $tab->addLink(Data::icon('department') . admin_trans_label('Department'), admin_route('organization.departments.index'));
                $tab->addLink(Data::icon('role') . admin_trans_label('Role'), admin_route('organization.roles.index'));
                $tab->addLink(Data::icon('permission') . admin_trans_label('Permission'), admin_route('organization.permissions.index'));
                $row->column(12, $tab);
            });
    }

    public function title()
    {
        return admin_trans_label('title');
    }

    /**
     * Make a grid builder.
     *
     * @return Grid
     */
    protected function grid(): Grid
    {
        return Grid::make(User::with(['roles', 'department']), function (Grid $grid) {
            $grid->column('id');
            $grid->column('username');
            $grid->column('name')->display(function ($name) {
                if ($this->ad_tag === 1) {
                    return "<span class='badge badge-primary mr-1'>AD</span>$name";
                }
                return $name;
            });
            $grid->column('gender');
            $grid->column('department.name');
            $grid->column('title');
            $grid->column('mobile');
            $grid->column('email');

            if (config('admin.permission.enable')) {
                $grid->column('roles')->pluck('name')->label('primary', 3);

                $permissionModel = config('admin.database.permissions_model');
                $roleModel = config('admin.database.roles_model');
                $nodes = (new $permissionModel())->allNodes();
                $grid->column('permissions')
                    ->if(function () {
                        return !$this->roles->isEmpty();
                    })
                    ->showTreeInDialog(function (Grid\Displayers\DialogTree $tree) use (&$nodes, $roleModel) {
                        $tree->nodes($nodes);

                        foreach (array_column($this->roles->toArray(), 'slug') as $slug) {
                            if ($roleModel::isAdministrator($slug)) {
                                $tree->checkAll();
                            }
                        }
                    })
                    ->else()
                    ->display('');
            }

            $grid->enableDialogCreate();
            $grid->disableDeleteButton();
            $grid->disableBatchDelete();

            $grid->showColumnSelector();
            $grid->hideColumns([
                'title',
                'mobile',
                'email'
            ]);

            $grid->batchActions([
                new UserBatchDeleteAction()
            ]);

            $grid->actions(function (RowActions $actions) {
                if (Admin::user()->can('user.record.delete')) {
                    $actions->append(new UserDeleteAction());
                }
            });

            $grid->toolsWithOutline(false);

            $grid->tools([
                new UserImportAction()
            ]);

            $grid->quickSearch('id', 'name', 'department.name', 'gender', 'title', 'mobile', 'email')
                ->placeholder(trans('main.quick_search'))
                ->auto(false);

            $grid->filter(function ($filter) {
                $filter->equal('department.name')->select(Department::pluck('name', 'id'));
            });

            $grid->export();
        });
    }

    /**
     * @param Request $request
     * @return mixed
     */
    public function selectList(Request $request)
    {
        $q = $request->get('q');

        return \App\Models\User::where('name', 'like', "%$q%")
            ->paginate(null, ['id', 'name as text']);
    }

    /**
     * Make a form builder.
     *
     * @return Form
     */
    public function form(): Form
    {
        return Form::make(User::with(['roles']), function (Form $form) {
            $userTable = config('admin.database.users_table');
            $connection = config('admin.database.connection');
            $id = $form->getKey();

            $form->display('id');
            $form->text('username', trans('admin.username'))
                ->required()
                ->creationRules(['required', "unique:{$connection}.{$userTable}"])
                ->updateRules(['required', "unique:{$connection}.{$userTable},username,$id"]);
            $form->text('name', trans('admin.name'))->required();
            $form->select('gender')
                ->options(Data::genders())
                ->required();
            if (Support::ifSelectCreate()) {
                $form->selectCreate('department_id', admin_trans_label('Department'))
                    ->options(Department::class)
                    ->ajax(admin_route('selection.organization.departments'))
                    ->url(admin_route('organization.departments.create'))
                    ->default(0);
            } else {
                $form->select('department_id', admin_trans_label('Department'))
                    ->options(Department::selectOptions())
                    ->required();
            }
            $form->divider();

            if ($id) {
                $form->password('password', trans('admin.password'))
                    ->minLength(5)
                    ->maxLength(20)
                    ->customFormat(function () {
                        return '';
                    })
                    ->attribute('autocomplete', 'new-password');
            } else {
                $form->password('password', trans('admin.password'))
                    ->required()
                    ->minLength(5)
                    ->maxLength(20)
                    ->attribute('autocomplete', 'new-password');
            }

            $form->password('password_confirmation', trans('admin.password_confirmation'))->same('password');

            $form->ignore(['password_confirmation']);

            if (config('admin.permission.enable')) {
                $form->multipleSelect('roles', trans('admin.roles'))
                    ->options(function () {
                        $roleModel = config('admin.database.roles_model');

                        return $roleModel::pluck('name', 'id');
                    })
                    ->customFormat(function ($v) {
                        return array_column($v, 'id');
                    });
            }

            $form->image('avatar', trans('admin.avatar'))->autoUpload();
            $form->text('title');
            $form->mobile('mobile');
            $form->email('email');

            $form->display('created_at');
            $form->display('updated_at');

            $form->disableDeleteButton();

            $form->disableCreatingCheck();
            $form->disableEditingCheck();
            $form->disableViewCheck();

            if ($id == \App\Models\User::DEFAULT_ID) {
                $form->disableDeleteButton();
            }
        })->saving(function (Form $form) {
            if ($form->password && $form->model()->get('password') != $form->password) {
                $form->password = bcrypt($form->password);
            }

            if (!$form->password) {
                $form->deleteInput('password');
            }
        });
    }

    /**
     * Make a show builder.
     *
     * @param mixed $id
     *
     * @return Show
     */
    protected
    function detail($id): Show
    {
        return Show::make($id, User::with(['roles', 'department']), function (Show $show) {
            $show->field('id');
            $show->field('name')->unescape()->as(function ($name) {
                if ($this->ad_tag === 1) {
                    return "<span class='badge badge-primary mr-1'>AD</span>$name";
                }
                return $name;
            });
            $show->field('avatar', __('admin.avatar'))->image();
            $show->field('department.name');
            $show->field('gender');
            $show->field('title');
            $show->field('mobile');
            $show->field('email');

            if (config('admin.permission.enable')) {
                $show->field('roles')->as(function ($roles) {
                    if (!$roles) {
                        return;
                    }

                    return collect($roles)->pluck('name');
                })->label();

                $show->field('permissions')->unescape()->as(function () {
                    $roles = $this->roles->toArray();

                    $permissionModel = config('admin.database.permissions_model');
                    $roleModel = config('admin.database.roles_model');
                    $permissionModel = new $permissionModel();
                    $nodes = $permissionModel->allNodes();

                    $tree = Tree::make($nodes);

                    $isAdministrator = false;
                    foreach (array_column($roles, 'slug') as $slug) {
                        if ($roleModel::isAdministrator($slug)) {
                            $tree->checkAll();
                            $isAdministrator = true;
                        }
                    }

                    if (!$isAdministrator) {
                        $keyName = $permissionModel->getKeyName();
                        $tree->check(
                            $roleModel::getPermissionId(array_column($roles, $keyName))->flatten()
                        );
                    }

                    return $tree->render();
                });
            }

            $show->field('created_at');
            $show->field('updated_at');

            $show->disableDeleteButton();
        });
    }
}
