<?php
namespace Src;
use PHPExcel;
use PHPExcel_IOFactory;
use PHPExcel_Reader_CSV;
use PHPExcel_Writer_Excel2007;
use PHPExcel_Writer_CSV;
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
            mkdir('reports/copypaste', 0777, true);
            mkdir('reports/phpmetrics', 0777, true);             
        }       
        
        copy('vendor/shital/gitpro/rulesets/phprcs.xml', 'reports/phprcs.xml');   
        copy('vendor/shital/gitpro/rulesets/phprmd.xml', 'reports/phprmd.xml');                 

        //generate Codesniffer report
        $codesnifferReport = 'reports/codesniffer/phpcssummary.csv';
        exec('php vendor/bin/phpcs -s --report=source --standard=reports/phprcs.xml app > '.$codesnifferReport);    
        //generate Mess detector report
        $messDetectorReport = 'reports/phpmd/phpmd.txt';        
        exec('php vendor/bin/phpmd app text reports/phprmd.xml > '.$messDetectorReport);

        $copyPasteReport = 'reports/copypaste/phpcpd.txt';        
        exec('php vendor/bin/phpcpd app > '.$copyPasteReport); 

        /*$phpMetricsReport = 'reports/phpmetrics/phpmetrics.html';        
        exec('php vendor/bin/phpmetrics --report-html='.$phpMetricsReport.' app');*/         

        self::convertReportToExcel($codesnifferReport,'reports/codesniffer/phpcssummary','reports/codesniffer/new-phpcssummary.csv',$messDetectorReport, $copyPasteReport);      
        return true;
    }

    public static function convertReportToExcel($csv_file, $xls_file, $new_file,$md_file,$cp_file)
    {
        $filename = $xls_file.'.xlsx';

        $filepath = $csv_file;
        $handle = fopen($filepath, "r");
        $lineNo = 0;
        $handle1 = fopen($new_file, 'a+');
        ftruncate($handle1, 0);
        if ($handle) {
            while (($line = fgets($handle)) !== false) {
            $lineNo++;
            if($lineNo >= 6){
                    // process the line read.
                $line = substr($line, 4);
                $line = preg_replace("/\s+/", ' ', $line);
                explode(" ",$line);
                if(count(explode(" ",$line)) == 3){
                    $handle2 = fopen($new_file, 'a+');
                    fwrite($handle2,$line. PHP_EOL);
                }
            }
            //echo $line. PHP_EOL;
            }

            fclose($handle);
        } else {
            // error opening the file.
        }         

        //-----Create a reader, set some parameters and read in the file-----
        $objReader = PHPExcel_IOFactory::createReader('CSV');
        $objReader->setDelimiter(' ');
        $objReader->setEnclosure('');
        //$objReader->setLineEnding("\r\n");
        $objReader->setSheetIndex(0);
        $objPHPExcel = $objReader->load($new_file);

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
        
        $multiplierNum = 5;
        $mulitplierArrayOfTwo = array('Generic.CodeAnalysis.ForLoopWithTestFunctionCall.NotAllowed',
                                      'Generic.CodeAnalysis.UselessOverridingMethod.Found',
                                      'Generic.ControlStructures.InlineControlStructure.Discouraged',
                                      'Generic.Files.LineLength.TooLong',
                                      'Generic.Metrics.CyclomaticComplexity.MaxExceeded',
                                      'Generic.Metrics.CyclomaticComplexity.TooHigh',
                                      'Generic.PHP.NoSilencedErrors.Discouraged',
                                      'Squiz.PHP.NonExecutableCode.ReturnNotRequired');
        
        //create new cell
        $totalCount = 20; 
        for($i=7;$i<=$totalCount;$i++){

            if(in_array($objPHPExcel->getActiveSheet()->getCell('a'.$i)->getValue(), $mulitplierArrayOfTwo)) {
                $multiplierNum = 2;
            } else if($objPHPExcel->getActiveSheet()->getCell('a'.$i)->getValue() == 'PHP Copy Paste detector Report') {
                $multiplierNum = 10;
            } 
            
            $objPHPExcel->getActiveSheet()->SetCellValue('C'.$i, $multiplierNum);
        }

        //multiply two cells
        for($j=7;$j<=$totalCount;$j++){
            $colD = ($objPHPExcel->getActiveSheet()->getCell('B'.$j)->getValue())*($objPHPExcel->getActiveSheet()->getCell('C'.$j)->getValue());
            $objPHPExcel->getActiveSheet()->SetCellValue('D'.$j, $colD);
        }

       //addition of cells
        $colDSum=0;
        for($k=7;$k<=$totalCount;$k++){
            $colDSum = ($objPHPExcel->getActiveSheet()->getCell('D'.$k)->getValue()) + $colDSum;
        }
        $objPHPExcel->getActiveSheet()->setCellValue('B6', 'Instance');
        $objPHPExcel->getActiveSheet()->setCellValue('C6', 'Multiplier');
        $objPHPExcel->getActiveSheet()->setCellValue('D6', 'Score');
        $objPHPExcel->getActiveSheet()->setCellValue('A5', 'Problems Score (0 is perfect, less is better)');
        $objPHPExcel->getActiveSheet()->SetCellValue('D5', $colDSum);
        $objPHPExcel->getActiveSheet()->setCellValue('A4', 'Grade - 10 (Perfect) to 0 (Worse) (Score out of 10)');

        if($colDSum == 0){
            $objPHPExcel->getActiveSheet()->SetCellValue('D4', '10');
        }elseif($colDSum <=10){
            $objPHPExcel->getActiveSheet()->SetCellValue('D4', '9');    
        }elseif($colDSum <=50){
            $objPHPExcel->getActiveSheet()->SetCellValue('D4', '8');
        }elseif($colDSum <=100){
            $objPHPExcel->getActiveSheet()->SetCellValue('D4', '7');
        }elseif($colDSum <=250){
            $objPHPExcel->getActiveSheet()->SetCellValue('D4', '6');
        }elseif($colDSum <=500){
            $objPHPExcel->getActiveSheet()->SetCellValue('D4', '5');
        }elseif($colDSum <=1000){
            $objPHPExcel->getActiveSheet()->SetCellValue('D4', '4');
        }elseif($colDSum <=1500){
            $objPHPExcel->getActiveSheet()->SetCellValue('D4', '3');
        }elseif($colDSum <=2000){
            $objPHPExcel->getActiveSheet()->SetCellValue('D4', '2');
        }elseif($colDSum <=2500){
            $objPHPExcel->getActiveSheet()->SetCellValue('D4', '1');
        }else{
            $objPHPExcel->getActiveSheet()->SetCellValue('D4', '0');
        } 


        //get mess detector count
        $filepath = $md_file;
        $handle = fopen($filepath, "r");
        $lineNo = 0;
        if ($handle) {
            while (($line = fgets($handle)) !== false) {
            $lineNo++;
            }
        fclose($handle);
        } else {
            // error opening the file.
        } 
        $objPHPExcel->getActiveSheet()->setCellValue('A55', 'PHP Mess detector Report');
        $objPHPExcel->getActiveSheet()->setCellValue('B55', $lineNo);
       

        //get copypaste detector count
        $filepath_cp = $cp_file;
        $handle = fopen($filepath_cp, "r");
        $lineNo = 0;
        if ($handle) {
            while (($line = fgets($handle)) !== false) {
            $lineNo++;
            }
        fclose($handle);
        } else {
            // error opening the file.
        }

        $objPHPExcel->getActiveSheet()->setCellValue('A57', 'PHP Copy Paste detector Report');
        $objPHPExcel->getActiveSheet()->setCellValue('B57', $lineNo);

        /*//get phpmetrics detector count
        $filepath_mt = $phpMetricsReport;
        $handle = fopen($filepath_mt, "r");
        $lineNo = 0;
        if ($handle) {
            while (($line = fgets($handle)) !== false) {
            $lineNo++;
            }
        fclose($handle);
        } else {
            // error opening the file.
        }

        $objPHPExcel->getActiveSheet()->setCellValue('A59', 'PHP PhpMetrics Report');
        $objPHPExcel->getActiveSheet()->setCellValue('B59', $lineNo);*/

        $objWriter2007->save("$filename");  //push out to the client browser
        
        return true;
    }
}