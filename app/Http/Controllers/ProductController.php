<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\Product;
use Illuminate\Http\Request;

class ProductController extends Controller
{
    public function index(Request $request)
    {
        $products = Product::all();
        return view('product.index', compact('products'));
    }

    public function checkout()
    {
        \Stripe\Stripe::setApiKey(env('STRIPE_SECRET_KEY'));

        $products = Product::all();
        $lineItems = [];
        $totalPrice = 0;
        foreach ($products as $product){
            $totalPrice += $product->price;
            $lineItems[] = [
                    'price_data' => [
                      'currency' => 'usd',
                      'product_data' => [
                        'name' => $product->name,
                        // 'image' => [$product->image]
                      ],  
                      'unit_amount' => $product->price * 100,
                    ],
                    'quantity' => 1,      
            ];
        }
        $session = \Stripe\Checkout\Session::create([
            'line_items' => $lineItems,
            'mode' => 'payment',
            'success_url' => route('checkout.success', [], true)."?session_id={CHECKOUT_SESSION_ID}",
            'cancel_url' => route('checkout.cancel', [], true)
          ]);

          $order = new Order();
          $order->status = 'unpaid';
          $order->total_price = $totalPrice;
          $order->session_id = $session->id;
          $order->save();

        return redirect($session->url);
    }

    public function success()
    {
        return view('product.checkout-success');
    }

    public function cancel()
    {
        
    }
}
