<?php
namespace Src;
use PHPExcel;
use PHPExcel_IOFactory;
use PHPExcel_CachedObjectStorageFactory;
use PHPExcel_Settings;
use Composer\Script\Event;
use Composer\Installer\PackageEvent;
require_once 'vendor/autoload.php';

class GenerateReportClass
{
    public static function generateReport(Event $event)
    {    	
        ob_start();
        if (!file_exists('reports')) {
    		mkdir('reports', 0777, true);  
    		self::createDir();
    		
		} else {
			if (!file_exists('reports/codesniffer')) {
				self::createDir();
			}
		}
        ob_end_flush();
		return true;
    }

    public static function createDir()
    {    	
    	mkdir('reports/codesniffer', 0777, true);
    	if (!file_exists('reports/phpmd'))
    	{
			mkdir('reports/phpmd', 0777, true); 
		}		
		
		copy('rulesets/phprcs.xml', 'reports/phprcs.xml');   
		copy('rulesets/phprmd.xml', 'reports/phprmd.xml');     			

		//generate Codesniffer report
		$codesnifferReport = 'reports/codesniffer/phpcs.csv';
		exec('php vendor/bin/phpcs --standard=reports/phprcs.xml app > '.$codesnifferReport);	

		//generate Mess detector report
		$messDetectorReport = 'reports/phpmd/phpmd.csv';		
		exec('php vendor/bin/phpmd app text reports/phprmd.xml > '.$messDetectorReport);

		self::convertReportToExcel($codesnifferReport,'reports/codesniffer/phpcs');
		self::convertReportToExcel($messDetectorReport,'reports/phpmd/phpmd');
		return true;
    }

    public static function convertReportToExcel($csv_file, $xls_file,$csv_enc = null)
    {
        //set cache
        $cacheMethod = PHPExcel_CachedObjectStorageFactory::cache_to_phpTemp;
        PHPExcel_Settings::setCacheStorageMethod($cacheMethod);

        //open csv file
        $objReader = new PHPExcel_Reader_CSV();
        if ($csv_enc != null)
            $objReader->setInputEncoding($csv_enc);
        $objPHPExcel = $objReader->load($csv_file);
        $in_sheet = $objPHPExcel->getActiveSheet();

        //open excel file
        $objPHPExcel = new PHPExcel();
        $out_sheet = $objPHPExcel->getActiveSheet();

        //row index start from 1
        $row_index = 0;
        foreach ($in_sheet->getRowIterator() as $row) {
            $row_index++;
            $cellIterator = $row->getCellIterator();
            $cellIterator->setIterateOnlyExistingCells(false);

            //column index start from 0
            $column_index = -1;
            foreach ($cellIterator as $cell) {
                $column_index++;
                $out_sheet->setCellValueByColumnAndRow($column_index, $row_index, $cell->getValue());
            }
        }

        //write excel file
        $objWriter = new PHPExcel_Writer_Excel2007($objPHPExcel);
        $objWriter->save($xls_file.'.xlsx');    
    	
        return true;
    }
}