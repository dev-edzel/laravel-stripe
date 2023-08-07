<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\Product;
use Exception;
use http\Env\Response;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class ProductController extends Controller
{
    public function index(Request $request)
    {
        $products = Product::all();
        return view('product.index', compact('products'));
    }

    public function checkout()
    {
        \Stripe\Stripe::setApiKey('sk_test_51NcJkhFJK6oGjToujz6HzEokn7KwX4SPb2XPytLXjnrTDoZxbrofsOZmcjfgkh1mAnGmSiuLMaJgByYAW1okEgEj00rjXzjmRl');

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

    public function success(Request $request)
    {
        \Stripe\Stripe::setApiKey('sk_test_51NcJkhFJK6oGjToujz6HzEokn7KwX4SPb2XPytLXjnrTDoZxbrofsOZmcjfgkh1mAnGmSiuLMaJgByYAW1okEgEj00rjXzjmRl');
        $sessionId = $request->get('session_id');

        try {
            $session = \Stripe\Checkout\Session::retrieve($sessionId);
            if (!$session) {
                throw new NotFoundHttpException;
            }
            $customer = \Stripe\Customer::retrieve($session->customer);

            $order = Order::where('session_id', $session->id)->first();
            if (!$order) {
                throw new NotFoundHttpException();
            }
            if ($order->status === 'unpaid') {
                $order->status = 'paid';
                $order->save();
            }

            return view('product.checkout-success', compact('customer'));
        } catch (Exception $e) {
            throw new NotFoundHttpException();
        }

    }

    public function cancel()
    {
        
    }
}
