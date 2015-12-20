<?php

namespace Bixie\Formmaker\Controller;

use Bixie\Formmaker\Type\Type;
use Pagekit\Application as App;
use Pagekit\Application\Exception;
use Bixie\Formmaker\Model\Field;
use Pagekit\User\Model\Role;

/**
 * @Route("field", name="field")
 * @Access("formmaker: manage forms")
 */
class FieldApiController {

	/**
	 * @Route("/", methods="GET")
	 * @Request({"form_id": "int"}, csrf=true)
	 */
	public function indexAction ($form_id) {
		if (!$form_id) {
			return [];
		}
		$query = Field::where(['form_id = ?'], [$form_id]);
		return array_values($query->get());
	}

	/**
	 * @Route("/", methods="POST")
	 * @Route("/{id}", methods="POST", requirements={"id"="\d+"})
	 * @Request({"field": "array", "id": "int"}, csrf=true)
	 */
	public function saveAction ($data, $id = 0) {

		if (!$field = Field::find($id)) {
			$field = Field::create();
			unset($data['id']);
		}

		if (!$data['slug'] = $this->slugify($data['slug'] ?: $data['label'])) {
			App::abort(400, __('Invalid slug.'));
		}


		try {

			$field->save($data);

		} catch (Exception $e) {
			App::abort(400, $e->getMessage());
		}

		return ['message' => 'success', 'field' => $field];
	}

	/**
	 * @Route("/edit")
	 * @Request({"id"})
	 */
	public function editAction ($id = '') {
		/** @var \Bixie\Formmaker\FormmakerModule $formmaker */
		$formmaker = App::module('bixie/formmaker');

		if (is_numeric($id)) {
			$field = Field::find($id);
		} else {
			$field = Field::create();
			$field->setType($id);
		}

		if (!$field) {
			App::abort(404, __('Field not found.'));
		}

		if (!$type = $formmaker->getFieldType($field->type)) {
			App::abort(404, __('Type not found.'));
		}
		//default values
		$fixedFields = ['multiple', 'required'];
		if (!$field->id) {
			foreach ($type->getConfig() as $key => $value) {
				if (!in_array($key, $fixedFields)) $field->set($key, $value);
			}
		}
		//check fixed value
		foreach ($fixedFields as $key) {
			if ($type[$key] != -1) {
				$field->set($key, $type[$key]);
			}
		}

		return [
			'field' => $field,
			'type' => $type,
			'roles' => array_values(Role::findAll())
		];
	}

	/**
	 * @Route("/updateOrder", methods="POST")
	 * @Request({"fields": "array"}, csrf=true)
	 */
	public function updateOrderAction ($fields = []) {
		foreach ($fields as $data) {
			if ($field = Field::find($data['id'])) {

				$field->priority = $data['order'];

				$field->save();
			}
		}

		return ['message' => 'success'];
	}

	/**
	 * @Route("/{id}", methods="DELETE", requirements={"id"="\d+"})
	 * @Request({"id": "int"}, csrf=true)
	 */
	public function deleteAction ($id) {
		if ($field = Field::find($id)) {

			$field->delete();
		}

		return ['message' => 'success'];
	}

	/**
	 * @Route("/bulk", methods="DELETE")
	 * @Request({"ids": "array"}, csrf=true)
	 */
	public function bulkDeleteAction ($ids = []) {
		foreach (array_filter($ids) as $id) {
			$this->deleteAction($id);
		}

		return ['message' => 'success'];
	}

	protected function slugify ($slug) {
		$slug = preg_replace('/\xE3\x80\x80/', ' ', $slug);
		$slug = str_replace('-', ' ', $slug);
		$slug = preg_replace('#[:\#\*"@+=;!><&\.%()\]\/\'\\\\|\[]#', "\x20", $slug);
		$slug = str_replace('?', '', $slug);
		$slug = trim(mb_strtolower($slug, 'UTF-8'));
		$slug = preg_replace('#\x20+#', '-', $slug);

		return $slug;
	}

}
