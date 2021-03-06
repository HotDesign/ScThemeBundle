<?php

/**
 * This file is part of the SimpleCatalog Frontend package.
 *
 * (c) HotDesign <info@hotdesign.com.ar>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace HotDesign\ScThemeBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use ConfigClasses\ItemTypes;
use ConfigClasses\MyConfig;

/**
 * ProductController is the main frontend controller to retrieve and display 
 * items profiles
 * 
 * @author    HotDesign info@hotdesign.com.ar
 * @copyright GPL-v2 2012/01/30
 * @package   ScThemeBundle
 * @version   0.1
 * 
 */
class ProductController extends Controller {

    /**
     * Retrieve and render the base entities listing by category, if slug is null, then retrieve all
     * items in all categories.
     * 
     * @return Response A Response instance 
     * 
     */
    public function indexAction($slug = NULL, $_format = 'html') {
        $level = 0; //default category level

        $em = $this->getDoctrine()->getEntityManager();
        //Get the ItemService
        $ItemService = $this->get('item.service');

        $category_repo = $em->getRepository('SimpleCatalogBundle:Category');
        $category = NULL;
       
        $category_id = NULL;

        if ($slug) {
            $category = $category_repo->findOneBySlug($slug);
            if (!$category) {
                throw $this->createNotFoundException('Unable to find Category entity.');
            }

            $category_id = $category->getId();
        }

        /**
         * @todo: make $max_items_per_page configurable
         */
        $current_page = (int) $this->getRequest()->get('page', 1);


        $service_output = $ItemService->getFullListing($category_id, $current_page);

        $entities = $service_output['entities'];
        $num_pages = $service_output['num_pages'];
        $pagerfanta = $service_output['pagerfanta'];
        unset($service_output);

        $output_tmp_entities = array();
         switch ($_format) {
             case 'xml':
             case 'json':
                 if ($entities) {
                     // view this in future
//                     /**
//                      *$serializer = new Serializer(array(new GetSetMethodNormalizer()), array('json' => new 
// JsonEncoder()));
// $json = $serializer->serialize($entity, 'json'); 
//                      */
                     foreach ($entities as $ext_entity) {
                         $entity = $ext_entity['BaseEntity'];
                         $tmp_category = $entity->getCategory();
                         
                         $tmp_entity = array(
                             'title' => $entity->getTitle(),
                             'id' => $entity->getid(),
                             'url' => $this->get('router')->generate('product_profile', array('slug' => $entity->getSlug(), 'category_slug' => $tmp_category->getSlug()), TRUE),
                             'description' => $entity->getDescription(),
                             'category' => $tmp_category->getTitle(),
                             'category_url' => $this->get('router')->generate('products_listing', array('slug' => $tmp_category->getSlug()), TRUE),
                             'created_at' => $entity->getCreatedAt(),
                             'updated_at' => $entity->getUpdatedAt(),
                         );

                         $tmp_entity['pics'] = array();

                         $tmp_pictures = $entity->getPics();
                         if (count($tmp_pictures) > 0) {
                             $default_pic = $entity->get_default_pic();
                             
                             $request = $this->get('request');
                             $web_url = $request->getScheme() . '://' . $request->getHttpHost() . $request->getBasePath().'/';
                             $tmp_entity['pics'][0] = $web_url.$default_pic->getWebPath();

                             foreach ($tmp_pictures as $pic) {
                                 if ($pic->getId() != $default_pic->getId()) {
                                     $tmp_entity['pics'][$pic->getId()] = $web_url.$pic->getWebPath();
                                 }
                             }
                         }

                         $output_tmp_entities[$entity->getId()] = $tmp_entity;
                     }
                 }
                
                 $entities = $output_tmp_entities;
                 break;
         }

        $to_render = array(
            'category_level' => $level,
            'category' => $category,
            'paginator' => $pagerfanta,
            'num_pages' => $num_pages,
            'entities' => $entities,
        );

        // if ($_format == 'json') {
        //     unset($to_render['paginator']);
        //     $to_render['to_render'] = $to_render;
        // }
        return $this->render("HotDesignScThemeBundle:Product:listing_entities.{$_format}.twig", $to_render);
    }

    public function profileAction($category_slug, $slug) {
        $em = $this->getDoctrine()->getEntityManager();
        $ItemService = $this->get('item.service');

        $entity = $ItemService->getFullItem($slug);

        if (empty($entity)) {
            throw $this->createNotFoundException('Unable to find Entity entity.');
        }
    
        $category = $entity['BaseEntity']->getCategory();

        $category_level = $category->getLvl();

        $to_render = array(
            'category_level' => $category_level,
            'category' => $category,
            'entity' => $entity['BaseEntity'],
            'pics' => $entity['pics'],
            'extends' => $entity['extends']
        );

        return $this->render('HotDesignScThemeBundle:Product:entity_profile.html.twig', $to_render);
    }

}
