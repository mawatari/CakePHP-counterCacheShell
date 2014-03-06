<?php
/**
 * CounterCache Shell
 *
 * Licensed under The MIT License
 * Redistributions of files must retain the above copyright notice.
 *
 * @copyright	Copyright (c) MAWATARI Naoto. (http://mawatari.jp)
 * @link		http://mawatari.jp
 * @version		0.01
 * @license		MIT License (http://www.opensource.org/licenses/mit-license.php)
 */
class CounterCacheShell extends AppShell {

/**
 * Contains tasks to load and instantiate
 *
 * @var array
 */
	public $tasks = ['DbConfig', 'Model'];

/**
 * The connection being used.
 *
 * @var string
 */
	public $connection = 'default';

/**
 * Holds the counter cache model names
 *
 * @var array
 */
	protected $_counterCacheModels = [];

/**
 * @return bool|int
 */
	public function main() {
		return $this->out($this->OptionParser->help());
	}

	public function update() {
//		if (empty($this->connection)) {
//			// DB設定を選択させる
//			$this->connection = $this->DbConfig->getConfig();
//		}

		$this->_getModel();
		$this->_outputModel();
		$selectedModel = $this->_selectModel();
		$this->_updateCounterCache($selectedModel);
	}

/**
 * カウンターキャッシュが設定されてるモデルを得る
 *
 */
	public function _getModel() {
		$tables = $this->Model->getAllTables($this->connection);
		$count = 1;

		foreach($tables as $table) {
			$model = $this->_modelName($table);
			$this->loadModel($model);
			if ($this->$model->belongsTo) {
				foreach ($this->$model->belongsTo as $parent => $assoc) {
					if (empty($assoc['counterCache'])) {
						continue;
					}
					$this->_counterCacheModels[$count] = ['model' => $model, 'parent' => $parent, 'foreignKey' => $assoc['foreignKey']];
					$count++;
				}
			}
		}
	}

/**
 * カウンターキャッシュが定義されているモデル一覧を標準出力に書き出す
 *
 */
	public function _outputModel() {
		if (empty($this->_counterCacheModels)) {
			$this->err('カウンターキャッシュが設定されているモデルが存在しません。');
			$this->_stop();
		}

		$modelNameMaxLength = Hash::apply($this->_counterCacheModels, '{n}.model', function($s){
			$ary = [];
			foreach ($s as $model) {
				$ary[] = strlen($model);
			}
			return max($ary);
		});
		$numberMaxLength = strlen(count($this->_counterCacheModels));
		$modelFieldMaxLength = $modelNameMaxLength + $numberMaxLength + 1;

		$this->out('counterCacheが設定されているモデル一覧');
		$this->hr();
		$this->out($this->_tableFormatter('#', $numberMaxLength + 2) . $this->_tableFormatter('Model', $modelFieldMaxLength) . 'belongsTo');
		$this->hr();
		foreach ($this->_counterCacheModels as $key => $val) {
			$this->out(sprintf("%${numberMaxLength}d. %s", $key, $this->_tableFormatter($val['model'], $modelNameMaxLength + 2) . $val['parent']));
		}
		$this->hr();
	}

/**
 * 対話形式でアップデートするモデルを選択する
 *
 */
	public function _selectModel() {
		$selectedModel = '';

		while (!$selectedModel) {
			$selectedModel = strtolower($this->in("アップデートするモデルのナンバーを入力してください。\n" .
				"q でキャンセル、a で全てのモデルをアップデートします。", null, 'q'));

			if ($selectedModel === 'q') {
				$this->out('キャンセルしました。');
				$this->_stop();
			}

			if (in_array($selectedModel, ['a', 'all'])) break;

			if (!$selectedModel
				|| intval($selectedModel) > count($this->_counterCacheModels)
				|| intval($selectedModel) <= 0
			) {
				$this->err('入力値が正しくありません。もう一度やり直してください。');
				$selectedModel = '';
			}
		}

		return $selectedModel;
	}

/**
 * カウンターキャッシュをアップデートする
 *
 * @param int|string $selectedModel
 */
	public function _updateCounterCache($selectedModel) {
		if (in_array($selectedModel, ['a', 'all'])) {
			$models = $this->_counterCacheModels;
		} else {
			$models[] = $this->_counterCacheModels[$selectedModel];
		}

		foreach ($models as $model) {
			$records = $this->{$model['parent']}->find('list');
			foreach($records as $key => $val) {
				$this->{$model['model']}->updateCounterCache([$model['foreignKey'] => $key]);
			}
		}
		$this->out('カウンターキャッシュをアップデートしました。');
	}

/**
 * テーブルレイアウト用に文字列の右側をホワイトスペースで埋める
 *
 * @param string $string
 * @param integer $width
 * @return string
 */
	public function _tableFormatter($string, $width = 0) {
		if (strlen($string) < $width) {
			$string = str_pad($string, $width, ' ');
		}

		return $string;
	}

/**
 * @return mixed
 */
	public function getOptionParser() {
		$parser = parent::getOptionParser();
		return $parser->description(
			'カウンターキャッシュ'
		)->addSubcommand(
			'update',
			[
				'help'   => 'カウンターキャッシュを更新',
				'parser' => [
					'description' => ['対話式でモデルを選択し、カウンターキャッシュの更新を行います']
				]
			]
		);
	}

}
