<?php

namespace App\Admin\Actions\Grid\RowAction;

use App\Admin\Forms\TodoRecordUpdateForm;
use Dcat\Admin\Admin;
use Dcat\Admin\Grid\RowAction;
use Dcat\Admin\Widgets\Modal;

class TodoRecordUpdateAction extends RowAction
{

    public function __construct($title = null)
    {
        parent::__construct($title);
        $this->title = '👨‍💼 ' . admin_trans_label('Update');
    }

    /**
     * 渲染模态框
     * @return Modal|string
     */
    public function render()
    {
        if (!Admin::user()->can('todo.record.update')) {
            return trans('main.unauthorized');
        }

        // 实例化表单类并传递自定义参数
        $form = TodoRecordUpdateForm::make()->payload(['id' => $this->getKey()]);

        return Modal::make()
            ->lg()
            ->title(admin_trans_label('Update'))
            ->body($form)
            ->button($this->title);
    }
}
