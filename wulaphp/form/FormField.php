<?php
/*
 * This file is part of wulacms.
 *
 * (c) Leo Ning <windywany@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace wulaphp\form;

use wulaphp\form\provider\FieldDataProvider;
use wulaphp\form\provider\JsonDataProvider;
use wulaphp\form\provider\LineDataProvider;
use wulaphp\form\provider\ParamDataProvider;
use wulaphp\form\provider\TableDataProvider;

/**
 * Class FormField
 * @package wulaphp\form
 */
abstract class FormField implements \ArrayAccess {
    /**
     * @var \wulaphp\form\IForm
     */
    protected $form = null;
    /**
     * @var \wulaphp\util\Annotation
     */
    protected $options = [];
    protected $value   = '';
    protected $name    = '';

    /**
     * FormField constructor.
     *
     * @param string              $name 表单字段名.
     * @param \wulaphp\form\IForm $form
     * @param array               $options
     */
    public function __construct(string $name, IForm $form, array $options = []) {
        $this->name    = $name;
        $this->form    = $form;
        $this->options = $options;
        /**@var \wulaphp\util\Annotation */
        $ann = $options['annotation'];
        if ($ann) {
            $opts ['label']      = $ann->getString('label', $ann->getDoc());
            $opts ['render']     = $ann->getString('render');
            $opts ['wrapper']    = $ann->getString('wrapper');
            $opts ['layout']     = $ann->getString('layout');
            $opts ['dataSource'] = $ann->getString('see', $ann->getString('dataSource', null));
            $opts ['dsCfg']      = $ann->getString('dsCfg', $ann->getString('data'));
            $opts ['note']       = $ann->getString('note');
            $opts1               = $ann->getJsonArray('option', []);
            $this->options       = array_merge($this->options, $opts, $opts1);
            $form->alterFieldOptions($name, $this->options);
        }
    }

    /**
     * 配置字段.
     *
     * @param string $name
     * @param mixed  $value
     *
     * @return $this
     */
    public function opt(string $name, $value): FormField {
        $this->options[ $name ] = $value;

        return $this;
    }

    /**
     * 通过数组配置字段.
     *
     * @param array $options
     *
     * @return $this
     */
    public function optionsByArray(array $options): FormField {
        if ($options) {
            $this->options = array_merge($this->options, $options);
        }

        return $this;
    }

    /**
     * 獲取值.
     *
     * @return string
     */
    public function getValue(): string {
        return $this->value;
    }

    /**
     * set the value of this field
     *
     * @param mixed $value
     */
    public function setValue($value) {
        $this->value = $value;
    }

    /**
     * 獲取布局信息。
     *
     * @return array
     */
    public function layout(): array {
        if (isset($this->options['layout'])) {
            return explode(',', $this->options['layout']);
        } else {
            return [];
        }
    }

    /**
     * 绘制.
     *
     * @param array $opts
     *
     * @return string
     */
    public function render($opts = []): string {
        if ($this->options['render'] && method_exists($this->form, $this->options['render'])) {
            return $this->form->{$this->options['render']}($this, $opts);
        } else {
            $html = $this->renderWidget($opts);
            if ($this->options['wrapper'] && method_exists($this->form, $this->options['wrapper'])) {
                $html = $this->form->{$this->options['wrapper']}($html, $this, $opts);
            }

            return $html;
        }
    }

    /**
     * 获取组件对应的js module
     * @return string
     */
    public function jsModule(): ?string {
        return null;
    }

    /**
     * 获取配置参数
     * @return array
     */
    public function getOptions() {
        return $this->options;
    }

    /**
     * 取数据提供器.
     *
     * @return \wulaphp\form\provider\FieldDataProvider
     */
    public function getDataProvider(): FieldDataProvider {
        $option = $this->options;
        $cfg    = isset($this->options['dsCfg']) ? $this->options['dsCfg'] : '';
        if (!is_array($cfg)) {
            $cfg = trim($cfg);
        }
        if (!isset($option['dataSource']) && is_string($cfg)) {
            $cfg1 = ltrim($cfg, ':');
            if (strlen($cfg) - strlen($cfg1) == 2) {
                return new FieldDataProvider($this->form, $this, $cfg1);
            }

            return FieldDataProvider::emptyDatasource();
        }

        $dsp = trim($option['dataSource'], '()\\');
        if ($dsp == 'json') {
            return new JsonDataProvider($this->form, $this, $cfg);
        }

        if ($dsp == 'table') {
            return new TableDataProvider($this->form, $this, $cfg);
        }

        if ($dsp == 'text') {
            return new LineDataProvider($this->form, $this, $cfg);
        }

        if ($dsp == 'param' || $dsp == 'parse_str') {
            return new ParamDataProvider($this->form, $this, $cfg);
        }

        if (!is_subclass_of($dsp, FieldDataProvider::class)) {
            return FieldDataProvider::emptyDatasource();
        }

        return new $dsp($this->form, $this, $cfg);
    }

    public function offsetExists($offset) {
        return isset($this->options[ $offset ]);
    }

    public function offsetGet($offset) {
        return $this->options[ $offset ];
    }

    public function offsetSet($offset, $value) {
        $this->options[ $offset ] = $value;
    }

    public function offsetUnset($offset) {
        unset($this->options[ $offset ]);
    }

    /**
     * 组件名称.
     *
     * @return string
     */
    public abstract function getName(): string;

    /**
     * 绘制.
     *
     * @param array $opts
     *
     * @return string
     */
    protected abstract function renderWidget(array $opts): string;

    /**
     * 字段配置表单.
     *
     * @return \wulaphp\form\IForm|null
     */
    public function getOptionForm(): ?IForm {
        return null;
    }
}