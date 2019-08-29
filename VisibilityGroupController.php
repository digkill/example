<?php

namespace backend\controllers\teacher;

use common\components\bl\VisibilityGroupManager;
use common\helpers\BackendUrl;
use Yii;
use yii\web\NotFoundHttpException;
use yii\web\ServerErrorHttpException;
use backend\controllers\BaseController;
use common\components\AjaxResponse;
use common\models\teacher\VisibilityGroup;
use common\models\teacher\VisibilityGroupSearch;
use common\models\teacher\VisibilityGroupForm;
use common\models\Grade;
use common\models\Student;

class VisibilityGroupController extends BaseController
{
    protected $manager = null;

    public function actionIndex()
    {
        $teacher = $this->actor;
        $manager = $this->getManager();
        $searchModel = new VisibilityGroupSearch();
        $searchModel->grades = array_keys($manager->getGradeList($teacher->id));
        $dataProvider = $searchModel->search(Yii::$app->request->queryParams);

        return $this->render('index', [
            'searchModel' => $searchModel,
            'dataProvider' => $dataProvider
        ]);
    }

    public function actionCheckBeforeCreate($redirectOnSuccess = true)
    {
        $teacher = $this->actor;
        $manager = $this->getManager();

        $gradeList = $manager->getGradeList($teacher->id);
        if (!$gradeList) {
            return $this->asJson(AjaxResponse::error('
                Sorry, you are not linked to any grade. Please, contact with your school administrator.
            '));
        }

        $relatedStudents = Student::find()->linkedWithTeacher($teacher->id)->all();
        if (!$relatedStudents) {
            return $this->asJson(AjaxResponse::error('
                Sorry, you have no students to assign. Please, contact with your school administrator.
            '));
        }

        $returnUrl = Yii::$app->request->post('returnUrl', BackendUrl::to(['index']));
        BackendUrl::remember($returnUrl);
        Yii::$app->session->set('returnUrl', $returnUrl);

        if ($redirectOnSuccess) {
            return $this->redirect(BackendUrl::to(['/teacher/visibility-group/create']));
        }
        return $this->asJson(AjaxResponse::success());
    }

    public function actionCreate()
    {
        $model = new VisibilityGroupForm();
        $visibilityGroup = new VisibilityGroup();
        $manager = $this->getManager();

        $returnUrl = Yii::$app->session->get('returnUrl', BackendUrl::to(['index']));

        if ($model->load(Yii::$app->request->post()) && $model->validate()) {
            $visibilityGroup->load($model->getAttributes(), '');
            if ($manager->save($visibilityGroup, $model)) {
                Yii::$app->session->set('newlyCreatedVisibilityGroupId', $visibilityGroup->id);
                return $this->redirect($returnUrl);
            }
        }

        $teacher = $this->actor;
        $gradeList = $manager->getGradeList($teacher->id);
        if (!$gradeList) {
            throw new ServerErrorHttpException(
                'Sorry, you are not linked to any grade. Please, contact with your school administrator.'
            );
        }
        $relatedGrades = $manager->getGradeQuery()->linkedWithTeacher($teacher->id)->all();
        $currentYearRelatedGrades = $manager->getGradeQuery()
            ->andWhere(['year' => Grade::getCurrentAcademicYear()])
            ->linkedWithTeacher($teacher->id)
            ->all();
        $memberedGradeList = $manager->getGradeMemberedList($relatedGrades);
        $memberedCurrentYearGradeList = $manager->getGradeMemberedList($currentYearRelatedGrades);

        $relatedStudents = Student::find()->linkedWithTeacher($teacher->id)->all();
        if (!$relatedStudents) {
            throw new ServerErrorHttpException(
                'Sorry, you have no students to assign. Please, contact with your school administrator.'
            );
        }

        $studentList = $manager->getStudentMemberedList($relatedStudents);

        $currentYearGrades = $manager->getCurrentYearGrades($teacher->id);

        return $this->render('create', [
            'model' => $model,
            'gradeList' => $gradeList,
            'studentList' => $studentList,
            'currentYearGrades' => $currentYearGrades,
            'memberedGradeList' => $memberedGradeList,
            'haveGradeMember' => false,
            'memberedCurrentYearGradeList' => $memberedCurrentYearGradeList,
        ]);
    }

    public function actionUpdate($id)
    {
        $model = new VisibilityGroupForm();
        $visibilityGroup = $this->findModel($id);
        $manager = $this->getManager();

        if (
            $model->load($visibilityGroup->getAttributes(), '') &&
            $model->load(Yii::$app->request->post()) &&
            $model->validate()
        ) {
            $visibilityGroup->load($model->getAttributes(), '');
            if ($manager->save($visibilityGroup, $model)) {
                return $this->redirect(['index']);
            }
        }

        $teacher = $this->actor;

        $gradeList = $manager->getGradeList($teacher->id);
        $currentYearGrades = $manager->getCurrentYearGrades($teacher->id);

        $relatedGrades = $manager->getGradeQuery()->linkedWithTeacher($teacher->id)->all();
        $currentYearRelatedGrades = $manager->getGradeQuery()
            ->andWhere(['year' => Grade::getCurrentAcademicYear()])
            ->linkedWithTeacher($teacher->id)
            ->all();
        $gradesInGroup = $manager->getGradeQuery()
            ->asArray()
            ->linkedWithTeacher($teacher->id)
            ->inVisibilityGroup($model->id)
            ->all();
        $memberedGradeList = $manager->getGradeMemberedList($relatedGrades, $gradesInGroup);
        $memberedCurrentYearGradeList = $manager->getGradeMemberedList($currentYearRelatedGrades, $gradesInGroup);

        $relatedStudents = Student::find()->linkedWithTeacher($teacher->id)->all();
        $groupMembers = Student::find()->asArray()->inVisibilityGroup($model->id)->all();
        $studentList = $manager->getStudentMemberedList($relatedStudents, $groupMembers);


        return $this->render('update', [
            'model' => $model,
            'gradeList' => $gradeList,
            'studentList' => $studentList,
            'currentYearGrades' => $currentYearGrades,
            'memberedGradeList' => $memberedGradeList,
            'haveGradeMember' => !empty($gradesInGroup),
            'memberedCurrentYearGradeList' => $memberedCurrentYearGradeList,
        ]);
    }

    /**
     * @param $id
     * @return \yii\web\Response
     * @throws \ErrorException
     * @throws \Throwable
     * @throws \yii\db\Exception
     */
    public function actionDelete($id)
    {
        $manager = $this->getManager();
        if ($manager->delete($id)) {
            Yii::$app->session->setFlash('success', 'Visibility Group successfully deleted.');
        } else {
            Yii::$app->session->setFlash('error', 'Visibility Group not deleted. Please try again later.');
        }
        return $this->redirect(['index']);
    }

    /**
     * @param $id
     * @return VisibilityGroup
     * @throws NotFoundHttpException
     */
    protected function findModel($id)
    {
        if ($visibilityGroup = VisibilityGroup::findOne($id)) {
            return $visibilityGroup;
        }

        throw new NotFoundHttpException('The requested page does not exist.');
    }


    protected function getManager()
    {
        if ($this->manager === null) {
            $this->manager = new VisibilityGroupManager(['actor' => $this->actor]);
        }
        return $this->manager;
    }

}
