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
        $objReader = PHPExcel_IOFactory::createReader('CSV');

        // If the files uses a delimiter other than a comma (e.g. a tab), then tell the reader
        $objReader->setDelimiter("\t");
        // If the files uses an encoding other than UTF-8 or ASCII, then tell the reader
        $objReader->setInputEncoding('UTF-16LE');

        $objPHPExcel = $objReader->load('MyCSVFile.csv');
        $objWriter = PHPExcel_IOFactory::createWriter($objPHPExcel, 'Excel5');
        $objWriter->save($xls_file.'.xls');
    	
        return true;
    }
}