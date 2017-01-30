<?php

namespace Src;

require_once 'vendor/autoload.php';
use PHPExcel\IOFactory;

use Composer\Script\Event;
use Composer\Installer\PackageEvent;

class GenerateReportClass
{
    public static function generateReport(Event $event)
    {
    	$composer = $event->getComposer();
        $event->getIO()->write("Show me after INSTALL command");
        if (!file_exists('reports')) {
    		mkdir('reports', 0777, true);  
    		self::createDir();
    		
		} else {
			if (!file_exists('reports/codesniffer')) {
				self::createDir();
			}
		}
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
		chmod('vendor/bin/phpcs', 0777); 
		exec('vendor/bin/phpcs --standard=reports/phprcs.xml app > '.$codesnifferReport);	

		//generate Mess detector report
		$messDetectorReport = 'reports/phpmd/phpmd.csv';
		chmod('vendor/bin/phpmd', 0777); 
		exec('vendor/bin/phpmd app text reports/phprmd.xml > '.$messDetectorReport);	    		

		//convert txt report file to excel
		header('Content-type: application/ms-excel');
		header('Content-Disposition: attachment; filename='.'test.xlsx');

		self::convertReportToExcel($codesnifferReport,'php://output');
		self::convertReportToExcel($messDetectorReport,'php://output');
		return true;
    }

    public static function convertReportToExcel($csv_file, $xls_file, $csv_enc=null)
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
        $objWriter->save($xls_file);
        return true;
    }
}