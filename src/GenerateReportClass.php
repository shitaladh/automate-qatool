<?php
namespace Src;
use PHPExcel;
use PHPExcel_IOFactory;
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

    public static function convertReportToExcel($csv_file, $xls_file)
    {
        $filename = $xls_file.'.xlsx';
        
        //-----Create a reader, set some parameters and read in the file-----
        $objReader = PHPExcel_IOFactory::createReader('CSV');
        $objReader->setDelimiter(' ');
        $objReader->setEnclosure('');
        $objReader->setLineEnding("\r\n");
        $objReader->setSheetIndex(0);
        $objPHPExcel = $objReader->load($csv_file);

        $objPHPExcel->getActiveSheet()->insertNewRowBefore(1, 6);
        $objPHPExcel->getActiveSheet()->getColumnDimension('A')->setWidth(60);
        //-----Create a Writer and output the file to the browser-----
        $objWriter2007 = PHPExcel_IOFactory::createWriter($objPHPExcel, 'Excel2007');
        $objPHPExcel->getActiveSheet()->getProtection()->setSort(true);

        $allDataInSheet = $objPHPExcel->getActiveSheet()->toArray(null,true,true,true);
        $sortArray = array();

        foreach($allDataInSheet as $person){
            foreach($person as $key=>$value){
                if(!isset($sortArray[$key])){
                    $sortArray[$key] = array();
                }
                $sortArray[$key][] = $value;
            }
        }

        $orderby = "A"; //change this to whatever key you want from the array

        array_multisort($sortArray[$orderby],SORT_ASC,$allDataInSheet);

        $objPHPExcel->getActiveSheet()->fromArray(
            $allDataInSheet,
            NULL,
            'A2'
        );
        $objWriter2007->save("$filename");  //push out to the client browser
    	
        return true;
    }
}