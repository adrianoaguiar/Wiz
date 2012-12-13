<?php

/**
 * Wiz
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Open Software License (OSL 3.0)
 * http://opensource.org/licenses/osl-3.0.php
 *
 * DISCLAIMER
 * 
 * This program is provided to you AS-IS.  There is no warranty.  It has not been
 * certified for any particular purpose.
 *
 * @package    Wiz
 * @author     Nick Vahalik <nick@classyllama.com>
 * @copyright  Copyright (c) 2012 Classy Llama Studios, LLC
 * @license    http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

/**
 * Product Plugin for Wiz
 *
 * @author Toon Van Dooren <toon.vandooren@phpro.be>
 */
Class Wiz_Plugin_Product extends Wiz_Plugin_Abstract {

    function _prefillUserData($options) {


        $returnArray = array(
            'number' => '',
            'type' => '',
            'category' => '',
            'image_x' => '',
            'image_y' => '',
        );

        if (count($options) == 5) {
            $returnArray['number'] = array_shift($options);
            $returnArray['type'] = array_shift($options);
            $returnArray['category'] = array_shift($options);
            $returnArray['image_x'] = array_shift($options);
            $returnArray['image_y'] = array_shift($options);
        }

        return $returnArray;
    }

    function createproductAction($options) {
        $defaults = $this->_prefillUserData($options);
        if (count($options) != 5) {
            do {
                printf('Number [%s]: ', $defaults['number']);
                $number = (($input = trim(fgets(STDIN))) != '' ? $input : $defaults['number']);
            } while ($number == '');

            do {
                printf('Type (only simple in this version)[%s]: ', $defaults['type']);
                $type = ($input = trim(fgets(STDIN))) != '' ? $input : $defaults['type'];
            } while ($type == '');
            do {
                printf('Category [%s]: ', $defaults['category']);
                $category = ($input = trim(fgets(STDIN))) != '' ? $input : $defaults['category'];
            } while ($category == '');
            do {
                printf('Image-x [%s]: ', $defaults['image_x']);
                $image_x = ($input = trim(fgets(STDIN))) != '' ? $input : $defaults['image_x'];
            } while ($image_x == '');
            do {
                printf('Image-y [%s]: ', $defaults['image_y']);
                $image_y = ($input = trim(fgets(STDIN))) != '' ? $input : $defaults['image_y'];
            } while ($image_y == '');
        } else {
            extract($defaults);
        }

        Wiz::getMagento();
        $categoryID = $this->checkCategory($category);
//$versionInfo = Mage::getVersionInfo();

        try {
            // REPLACE BELOW WITH LOREMPIXEL IMAGES.
            if (!is_dir(Mage::getBaseDir('media') . '/wizimages')) {
                mkdir(Mage::getBaseDir('media') . '/wizimages');
                $content = file_get_contents("http://lorempixel.com/625/625/food/sample");
                file_put_contents(Mage::getBaseDir('media') . "/wizimages/625x625.jpg", $content);
                echo "Creating imagesfolder and default image..." . PHP_EOL;
            }
            $indexer = Mage::getSingleton('index/indexer');
            $indexer->lockIndexer();
            echo "Adding products to " . $category . "..." . PHP_EOL;
            if ($type == "simple") {
                for ($i = 1; $i <= $number; $i++) {
                    $product = Mage::getModel('catalog/product');
                    $product->setSku('Wizdemoproduct' . rand(1, 1000000) * $i)
                            ->setName('Wizdemoproduct' . rand(1, 1000000) * $i)
                            ->setTypeId(Mage_Catalog_Model_Product_Type::TYPE_SIMPLE)
                            ->setStatus(Mage_Catalog_Model_Product_Status::STATUS_ENABLED)
                            ->setVisibility(Mage_Catalog_Model_Product_Visibility::VISIBILITY_BOTH)
                            ->setCategoryIds($categoryID)
                            ->setQty('500')
                            ->setisInStock('true')
                            ->setWebsiteIds(array(1))
                            ->setWeight(rand(1, 1000))
                            ->setTaxClassId(2)
                            ->setPrice(rand(1, 1000))
                            ->setDescription("Lorem ipsum dolor sit amet, consectetur adipiscing elit. Vestibulum id condimentum tortor. Pellentesque mollis egestas tellus id tempus. Mauris elit risus, vulputate in condimentum non, imperdiet vel dui. Proin aliquam nulla at arcu lobortis hendrerit. Suspendisse aliquet rutrum semper. Nam pulvinar vestibulum nisl sit amet ultrices. Nulla eget ligula a nunc commodo tristique.")
                            ->setShortDescription("This is a testproduct provided by PHPro")
                            ->setAttributeSetId($product->getResource()->getEntityType()->getDefaultAttributeSetId());
                    $product->setStockData(array('is_in_stock' => 1, 'qty' => 50));
                    $this->addImagesToProduct($product, $image_x, $image_y, $i);
                    $product->save();
                    unset($product);
                }
            } else {
                die("simple is the only option atm" . PHP_EOL);
            }
            echo "Imported " . $number . " " . $type . " products!" . PHP_EOL;
            echo "Starting reindex" . "..." . PHP_EOL;
            $indexer->unlockIndexer();
            $processes = $indexer->getProcessesCollection();
            foreach ($processes as $process) {
                $process->reindexEverything();
            }
            echo "Done!" . PHP_EOL;
        } catch (Exception $e) {
            throw $e;
        }
    }

    function checkCategory($category) {
        if ($category == "default") {
            $rootCategory = Mage::getModel('catalog/category')->load(Mage::app()->getWebsite(true)->getDefaultStore()->getRootCategoryId());
            $categoryID = $rootCategory->getId();
            unset($rootCategory);
        } else {
            $catcheck = Mage::getModel('catalog/category')->getCollection()
                    ->addAttributeToFilter('is_active', true)
                    ->addAttributeToFilter('name', $category)
                    ->getFirstItem();
            if ($catcheck->getId()) {
                $categoryID = $catcheck->getId();
                unset($catcheck);
            } else {
                $categoryID = $this->createCategory($category);
            }
        }

        return $categoryID;
    }

    function createCategory($categoryname) {
        echo "Creating category " . $categoryname . "..." . PHP_EOL;
        $parentId = Mage::app()->getWebsite(true)->getDefaultStore()->getRootCategoryId();
        $category = new Mage_Catalog_Model_Category();
        $category->setName($categoryname);
        $category->setUrlKey($categoryname);
        $category->setIsActive(1);
        $category->setDisplayMode('PRODUCTS_AND_PAGE');
        $category->setIsAnchor(0);
        $parentCategory = Mage::getModel('catalog/category')->load($parentId);
        $category->setPath($parentCategory->getPath());
        $category->save();
        return $category->getId();
        unset($category);
    }

    function addImagesToProduct($product, $image_x, $image_y, $i) {
        $imagedir = Mage::getBaseDir('media') . '/wizimages/';
        if (($image_x == "625" && $image_y == "625") || ($image_x == "default" && $image_y == "default")) {
            $imagesize_x = "625";
            $imagesize_y = "625";
        } else {
            $imagesize_x = $image_x;
            $imagesize_y = $image_y;
            if ($i == 1) {
                $content = file_get_contents("http://lorempixel.com/" . $image_x . "/" . $image_y . "/food/sample");
                file_put_contents($imagedir . $image_x . "x" . $image_y . ".jpg", $content);
            }
            echo "Adding new image..." . PHP_EOL;
        }

        $mediaArray = array(
            'thumbnail' => $imagedir . $imagesize_x . "x" . $imagesize_y . ".jpg",
            'small_image' => $imagedir . $imagesize_x . "x" . $imagesize_y . ".jpg",
            'image' => $imagedir . $imagesize_x . "x" . $imagesize_y . ".jpg",
        );
        foreach ($mediaArray as $imageType => $filePath) {
            try {
                $product->addImageToMediaGallery($filePath, $imageType, false);
            } catch (Exception $e) {
                echo $e->getMessage() . PHP_EOL;
            }
        }
    }

}

