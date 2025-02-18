<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class ProductApiController extends Controller
{
    /**
     * Display a product list based on query.
     *
     * @return \Illuminate\Http\Response
     */
    public function getProduct(Request $request)
    {
        //get the product data
        $products = file_get_contents("https://draft.grebban.com/backend/products.json");
        $products_data = json_decode($products, true);

        //get attribyte meta data
        $attribute_meta = file_get_contents("https://draft.grebban.com/backend/attribute_meta.json");
        $attribute_meta_data = json_decode($attribute_meta, true);

        $page = $request->query('page') ? : 1;
        $page_size = $request->query('page_size') ? : count($products_data);

        //sort the products based on name ASC
        usort($products_data, function($a, $b){ return strcmp( $a['name'], $b['name'] ); });

        $page_data = [];
        for( $i = 0; $i < $page_size; $i++){
            $index = ($page - 1) * $page_size;

            if($i + $index < count($products_data)){
                $page_data[] = $products_data[$i + $index];
            }
        }

        //loop through each product and attributes to remap them
        foreach ($page_data as &$product) {
            $product['attributes'] = $this->remapProductAttributes($product['attributes'], $attribute_meta_data);
        }

        return response()->json([
            'products' => $page_data, 
            'page' => $page, 
            'totalPages'=> ceil(count($products_data) / $page_size)
        ], 200);
    }

    /**
     * Remap the attribute values for a single product
     */
    public function remapProductAttributes($product_attributes, $attribute_meta_data){
        $output = [];//to hold the remapped values

        foreach ($product_attributes as $key => $value) {
            foreach($attribute_meta_data as $attribute){

                //check if attribute is color
                if($attribute['code'] == $key && $attribute['code'] == 'color'){
                    $colors = explode(",", $value);

                    foreach($attribute['values'] as $newValues){
                        if(in_array($newValues['code'], $colors)){
                            $output[] = ['name'=> $attribute['name'], 'value'=> $newValues['name']];
                        }
                    }
                }

                //check if attribute is category
                if($attribute['code'] == $key && $attribute['code'] == 'cat'){
                    $categories = explode(",", $value);

                    foreach($categories as $cat){
                        $cat = explode("_", $cat);
                        $newValue = '';
                        $cat_lookup = 'cat_';

                        for($i = 1; $i < count($cat); $i++){
                            $cat_lookup = $cat_lookup . $cat[$i];

                            foreach($attribute['values'] as $newValues){
                                if($cat_lookup == $newValues['code']){
                                    $newValue = $newValue . $newValues['name'];
                                }
                            }

                            if($i+1 < count($cat)) {
                                $newValue = $newValue . ' > ';
                                $cat_lookup = $cat_lookup . '_';
                            }
                        }

                        $output[] = ['name'=> $attribute['name'], 'value'=> $newValue];
                    }
                }
            }
        }

        return $output;
    }
}
