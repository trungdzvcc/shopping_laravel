<?php

namespace App\Http\Controllers;

use App\Category;
use Illuminate\Http\Request;
use App\Components\Recusive;
use App\Http\Requests\ProductAddRequest;
use App\Product;
use App\ProductImage;
use App\ProductTag;
use App\Tag;
use App\Traits\DeleteModelTrait;
use App\Traits\StorageImageTrait;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class AdminProductController extends Controller
{
    use StorageImageTrait;
    private $category;
    private $product;
    private $productImage;
    private $productTag;
    private $tag;

    public function __construct(
        Category $category,
        Product $product,
        Tag $tag,
        ProductTag $productTag,
        ProductImage $productImage
    ) {
        $this->category = $category;
        $this->product = $product;
        $this->productImage = $productImage;
        $this->tag = $tag;
        $this->productTag = $productTag;
    }
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        //
        $products = $this->product->latest()->paginate(5);
        return view('admin.product.index', compact('products'));
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        //
        $htmlOption = $this->getCate($parent_id = '');
        return view('admin.product.add', compact('htmlOption'));
    }

    public function getCate($parent_id)
    {
        $data = $this->category->all();
        $recusive = new Recusive($data);
        $htmlOption = $recusive->categoryRecusive($parent_id);

        return $htmlOption;
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(ProductAddRequest $request)
    {
        //
        try {
            DB::beginTransaction();
            $dataProductCreate = [
                'name' => $request->name,
                'price' => $request->price,
                'content' => $request->contents,
                'user_id' => Auth::id(),
                'category_id' => $request->category_id,
            ];
            

            $dataUploadFeatureImage = $this->storageTraitUpload($request, 'feature_image_path', 'product');
          
            if (!empty($dataUploadFeatureImage)) {
                $dataProductCreate['feature_image_name'] = $dataUploadFeatureImage['file_name'];
                $dataProductCreate['feature_image_path'] = $dataUploadFeatureImage['file_path'];
              
            }
            
            $product = $this->product->create($dataProductCreate);
            echo("Completed data product");
     
            //insert data to product_images
            if ($request->hasFile('image_path')) {
                foreach ($request->image_path as $fileItem) {
                    $dataProductImageDetails = $this->storageTraitUploadMultiple($fileItem, 'product');
                    $product->images()->create([
                        'image_path' => $dataProductImageDetails['file_path'],
                        'image_name' => $dataProductImageDetails['file_name'],
                    ]);
                }
                echo(" --Insert image"); 
            }
            
            // insert tags to for product 
            $tagIds = [];
            if (!empty($request->tags)) {
                foreach ($request->tags as $tagItem) {
                    $tagInstance = $this->tag->firstOrCreate(['name' => $tagItem]);
                    $tagIds[] = $tagInstance->id;
                }
                echo("-- insert tags");
            }
            $product->tags()->attach($tagIds);
            DB::commit();
            echo("--Redirect index");
            return redirect()->route('product.index');
            
        } catch (\Exception $exception) {
            DB::rollBack();
            Log::error('Message: ' . $exception->getMessage() . ' Line : ' . $exception->getLine());
        }
        echo("qua store---");
        $string=implode("-",$dataProductCreate);
        echo($string);
        return redirect()->route('product.index');
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function edit($id)
    {
        //
        $product = $this->product->find($id);
        $htmlOption = $this->getCate($product->category_id);
        return view('admin.product.edit', compact('htmlOption', 'product'));
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id)
    {
        //
        try {
            DB::beginTransaction();
            $dataProductUpdate = [
                'name' => $request->name,
                'price' => $request->price,
                'content' => $request->contents,
                'user_id' => Auth::id(),
                'category_id' => $request->category_id,
            ];

            $dataUploadFeatureImage = $this->storageTraitUpload($request, 'feature_image_path', 'product');
            if (!empty($dataUploadFeatureImage)) {
                $dataProductUpdate['feature_image_name'] = $dataUploadFeatureImage['file_name'];
                $dataProductUpdate['feature_image_path'] = $dataUploadFeatureImage['file_path'];
            }

            $this->product->find($id)->update($dataProductUpdate);
            $product = $this->product->find($id);

            //insert data to product_images
            if ($request->hasFile('image_path')) {
                $this->productImage->where('product_id', $id)->delete();
                foreach ($request->image_path as $fileItem) {
                    $dataProductImageDetails = $this->storageTraitUploadMultiple($fileItem, 'product');
                    $product->images()->create([
                        'image_path' => $dataProductImageDetails['file_path'],
                        'image_name' => $dataProductImageDetails['file_name'],
                    ]);
                }
            }
            // insert tags to for product 
            $tagIds = [];
            if (!empty($request->tags)) {
                foreach ($request->tags as $tagItem) {
                    $tagInstance = $this->tag->firstOrCreate(['name' => $tagItem]);
                    $tagIds[] = $tagInstance->id;
                }

            }

            $product->tags()->sync($tagIds);
            DB::commit();
            return redirect()->route('product.index');
        } catch (\Exception $exception) {
            DB::rollBack();
            Log::error('Message: ' . $exception->getMessage() . ' Line : ' . $exception->getLine());
        }
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    use DeleteModelTrait;
    public function destroy($id)
    {
        //
        return $this->deleteModelTrait($id,$this->product);
    }
}
