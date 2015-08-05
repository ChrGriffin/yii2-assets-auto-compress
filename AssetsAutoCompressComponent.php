<?php
/**
 * @author Semenov Alexander <semenov@skeeks.com>
 * @link http://skeeks.com/
 * @copyright 2010 SkeekS (�����)
 * @date 05.08.2015
 */
namespace skeeks\yii2\assetsAuto;

use skeeks\cms\helpers\FileHelper;
use yii\base\BootstrapInterface;
use yii\base\Component;
use yii\base\Event;
use yii\helpers\ArrayHelper;
use yii\helpers\Html;
use yii\helpers\Url;
use yii\web\Application;
use yii\web\Response;
use yii\web\View;

/**
 * Class AssetsAutoCompressComponent
 * @package skeeks\yii2\assetsAuto
 */
class AssetsAutoCompressComponent extends Component implements BootstrapInterface
{
    /**
     * @var bool ��������� ���������� ��������� ����������
     */
    public $enabled = true;



    /**
     * @var bool
     */
    public $jsCompress = true;
    /**
     * @var bool �������� ����������� ��� ��������� js
     */
    public $jsCompressFlaggedComments = true;




    /**
     * @var bool ��������� ����������� css ������
     */
    public $cssFileCompile = true;

    /**
     * @var bool �������� �������� ����� css � ������� ������ ���� ��� � ���������� �����, ������ ��� � ����.
     */
    public $cssFileRemouteCompile = false;



    /**
     * @var bool ��������� ����������� js ������
     */
    public $jsFileCompile = true;

    /**
     * @var bool �������� �������� ����� js � ������� ������ ���� ��� � ���������� �����, ������ ��� � ����.
     */
    public $jsFileRemouteCompile = false;

    /**
     * @var bool �������� ������ � ��������� js ����� ����������� � ����
     */
    public $jsFileCompress = true;

    /**
     * @var bool �������� ����������� ��� ��������� js
     */
    public $jsFileCompressFlaggedComments = true;



    /**
     * @param \yii\web\Application $app
     */
    public function bootstrap($app)
    {
        if ($app instanceof Application)
        {
            //Response::EVENT_AFTER_SEND,
            //$content = ob_get_clean();
            $app->view->on(View::EVENT_END_PAGE, function(Event $e)
            {
                include_once __DIR__ . '/libs/minify-2.1.7/min/lib/Minify/Loader.php';
                \Minify_Loader::register();

                /**
                 * @var $view View
                 */
                $view = $e->sender;

                if ($this->enabled && $view instanceof View && \Yii::$app->response->format == Response::FORMAT_HTML && !\Yii::$app->request->isAjax && !\Yii::$app->request->isPjax)
                {
                    \Yii::beginProfile('Compress assets');
                    $this->_processing($view);
                    \Yii::endProfile('Compress assets');
                }
            });

            /*$app->response->on(Response::EVENT_AFTER_PREPARE, function(Event $e)
            {
                /**
                 * @var $response Response
                $response = $e->sender;
                if ($response->format == Response::FORMAT_HTML)
                {
                    //������� ��������
                    //$response->content = '111';
                    //print_r($response);die;
                }
            });*/
        }
    }

    /**
     * @param View $view
     */
    protected function _processing(View $view)
    {
        //���������� ������ js � ����.
        if ($view->jsFiles && $this->jsFileCompile)
        {
            \Yii::beginProfile('Compress js files');
            foreach ($view->jsFiles as $pos => $files)
            {
                if ($files)
                {
                    $view->jsFiles[$pos] = $this->_processingJsFiles($files);
                }
            }
            \Yii::endProfile('Compress js files');
        }

        //���������� js ���� ������� ����������� �� ��������
        if ($view->js && $this->jsCompress)
        {
            \Yii::beginProfile('Compress js code');
            foreach ($view->js as $pos => $parts)
            {
                if ($parts)
                {
                    $view->js[$pos] = $this->_processingJs($parts);
                }
            }
            \Yii::endProfile('Compress js code');
        }

        //���������� css ������ ������� ����������� �� ��������
        if ($view->cssFiles && $this->cssFileCompile)
        {
            \Yii::beginProfile('Compress css files');
            $view->cssFiles = $this->_processingCssFiles($view->cssFiles);
            \Yii::endProfile('Compress css files');
        }
    }

    /**
     * @param $parts
     * @return array
     * @throws \Exception
     */
    protected function _processingJs($parts)
    {
        $result = [];

        if ($parts)
        {
            foreach ($parts as $key => $value)
            {
                $result[$key] = \JShrink\Minifier::minify($value, ['flaggedComments' => $this->jsCompressFlaggedComments]);
            }
        }

        return $result;
    }

    /**
     * @param array $files
     * @return array
     */
    protected function _processingJsFiles($files = [])
    {
        $fileName   =  md5( implode(array_keys($files)) ) . '.js';
        $publicUrl  = \Yii::getAlias('@web/assets/js-compress/' . $fileName);

        $rootDir    = \Yii::getAlias('@webroot/assets/js-compress');
        $rootUrl    = $rootDir . '/' . $fileName;

        if (file_exists($rootUrl))
        {
            $resultFiles        = [];

            foreach ($files as $fileCode => $fileTag)
            {
                if (!Url::isRelative($fileCode))
                {
                    $resultFiles[$fileCode] = $fileTag;
                } else
                {
                    if ($this->jsFileRemouteCompile)
                    {
                        $resultFiles[$fileCode] = $fileTag;
                    }
                }
            }

            $publicUrl                  = $publicUrl . "?v=" . filemtime($rootUrl);
            $resultFiles[$publicUrl]    = Html::jsFile($publicUrl);
            return $resultFiles;
        }

        $resultContent  = [];
        $resultFiles    = [];
        foreach ($files as $fileCode => $fileTag)
        {
            if (Url::isRelative($fileCode))
            {
                $resultContent[] = trim(file_get_contents( Url::to(\Yii::getAlias('@web' . $fileCode), true) ));
            } else
            {
                if ($this->jsFileRemouteCompile)
                {
                    //�������� ������� ��������� ����
                    $resultContent[] = trim(file_get_contents( $fileCode ));
                } else
                {
                    $resultFiles[$fileCode] = $fileTag;
                }
            }

        }

        if ($resultContent)
        {
            $content = implode($resultContent, ";\n");
            if (!is_dir($rootDir))
            {
                if (!FileHelper::createDirectory($rootDir, 0777))
                {
                    return $files;
                }
            }

            if ($this->jsFileCompress)
            {
                $content = \JShrink\Minifier::minify($content, ['flaggedComments' => $this->jsFileCompressFlaggedComments]);
            }

            $file = fopen($rootUrl, "w");
            fwrite($file, $content);
            fclose($file);
        }


        if (file_exists($rootUrl))
        {
            $publicUrl                  = $publicUrl . "?v=" . filemtime($rootUrl);
            $resultFiles[$publicUrl]    = Html::jsFile($publicUrl);
            return $resultFiles;
        } else
        {
            return $files;
        }
    }

    /**
     * @param array $files
     * @return array
     */
    protected function _processingCssFiles($files = [])
    {
        $fileName   =  md5( implode(array_keys($files)) ) . '.css';
        $publicUrl  = \Yii::getAlias('@web/assets/css-compress/' . $fileName);

        $rootDir    = \Yii::getAlias('@webroot/assets/css-compress');
        $rootUrl    = $rootDir . '/' . $fileName;

        if (file_exists($rootUrl))
        {
            $resultFiles        = [];

            foreach ($files as $fileCode => $fileTag)
            {
                if (!Url::isRelative($fileCode))
                {
                    $resultFiles[$fileCode] = $fileTag;
                } else
                {
                    if ($this->cssFileRemouteCompile)
                    {
                        $resultFiles[$fileCode] = $fileTag;
                    }
                }
            }

            $publicUrl                  = $publicUrl . "?v=" . filemtime($rootUrl);
            $resultFiles[$publicUrl]    = Html::cssFile($publicUrl);
            return $resultFiles;
        }

        $resultContent  = [];
        $resultFiles    = [];
        foreach ($files as $fileCode => $fileTag)
        {
            if (Url::isRelative($fileCode))
            {
                $contentTmp         = trim(file_get_contents( Url::to(\Yii::getAlias('@web' . $fileCode), true) ));

                $fileCodeTmp = explode("/", $fileCode);
                unset($fileCodeTmp[count($fileCodeTmp) - 1]);
                $prependRelativePath = implode("/", $fileCodeTmp) . "/";

                $contentTmp    = \Minify_CSS::minify($contentTmp, [
                    "prependRelativePath" => $prependRelativePath,

                    'compress' => false,
                    'removeCharsets' => false,
                    'preserveComments' => false,
                ]);

                $resultContent[] = $contentTmp;
            } else
            {
                if ($this->cssFileRemouteCompile)
                {
                    //�������� ������� ��������� ����
                    $resultContent[] = trim(file_get_contents( $fileCode ));
                } else
                {
                    $resultFiles[$fileCode] = $fileTag;
                }
            }

        }

        if ($resultContent)
        {
            $content = implode($resultContent, "\n");
            if (!is_dir($rootDir))
            {
                if (!FileHelper::createDirectory($rootDir, 0777))
                {
                    return $files;
                }
            }

            $content = \CssMin::minify($content);

            $file = fopen($rootUrl, "w");
            fwrite($file, $content);
            fclose($file);
        }


        if (file_exists($rootUrl))
        {
            $publicUrl                  = $publicUrl . "?v=" . filemtime($rootUrl);
            $resultFiles[$publicUrl]    = Html::cssFile($publicUrl);
            return $resultFiles;
        } else
        {
            return $files;
        }
    }


}