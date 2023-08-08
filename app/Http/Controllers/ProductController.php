<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\Product;
use Exception;
use Illuminate\Http\Request;
use Stripe\Customer;
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
        \Stripe\Stripe::setApiKey("sk_test_51NcJkhFJK6oGjToujz6HzEokn7KwX4SPb2XPytLXjnrTDoZxbrofsOZmcjfgkh1mAnGmSiuLMaJgByYAW1okEgEj00rjXzjmRl");

        $products = Product::all();
        $lineItems = [];
        $totalPrice = 0;
        foreach ($products as $product) {
            $totalPrice += $product->price;
            $lineItems[] = [
                'price_data' => [
                    'currency' => 'usd',
                    'product_data' => [
                        'name' => $product->name,
                        'images' => [$product->image]
                    ],
                    'unit_amount' => $product->price * 100,
                ],
                'quantity' => 1,
            ];
        }
        $session = \Stripe\Checkout\Session::create([
            'line_items' => $lineItems,
            'mode' => 'payment',
            'success_url' => route('checkout.success', [], true) . "?session_id={CHECKOUT_SESSION_ID}",
            'cancel_url' => route('checkout.cancel', [], true),
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
        \Stripe\Stripe::setApiKey("sk_test_51NcJkhFJK6oGjToujz6HzEokn7KwX4SPb2XPytLXjnrTDoZxbrofsOZmcjfgkh1mAnGmSiuLMaJgByYAW1okEgEj00rjXzjmRl");
        $sessionId = $request->get('session_id');

        try {
            $session = \Stripe\Checkout\Session::retrieve($sessionId);
            if (!$session) {
                throw new NotFoundHttpException;
            }

            $customer = null;
            if ($session->customer) {
                $customer = \Stripe\Customer::retrieve($session->customer);
                if (!$customer) {
                    throw new NotFoundHttpException;
                }
            }

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
            // You can log the error here for debugging purposes
            throw new NotFoundHttpException();
        }
    }

    public function cancel()
    {

    }


    public function webhook()
    {
        $endpoint_secret = ("whsec_4e2407f0748e1b1b77050897d5ca7da0af7674e88b75f21a73ebe072c1daa8fa");

        $payload = @file_get_contents('php://input');
        $sig_header = $_SERVER['HTTP_STRIPE_SIGNATURE'];
        $event = null;

        try {
            $event = \Stripe\Webhook::constructEvent(
                $payload, $sig_header, $endpoint_secret
            );
        } catch (\UnexpectedValueException $e) {
            return response('', 400);
        } catch (\Stripe\Exception\SignatureVerificationException $e) {
            return response('', 400);
        }

        switch ($event->type) {
            case 'checkout.session.completed':
                $session = $event->data->object;

                $order = Order::where('session_id', $session->id)->first();
                if ($order && $order->status === 'unpaid') {
                    $order->status = 'paid';
                    $order->save();
                }
            default:
                echo 'Received unknown event type ' . $event->type;
        }

        return response('');
    }
}
