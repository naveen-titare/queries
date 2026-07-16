<?php

namespace Database\Seeders;

use App\Models\SendVoucherProduct;
use App\Models\SendVoucherCode;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class SendVoucherDummySeeder extends Seeder
{
    public function run(): void
    {
        $base = [
            ['Taj Hotels','Taj Hotels',[2000,5000,10000,25000],'offline'],
            ['AJIO','AJIO',[500,1000,2500],'both'],
            ['Nykaa','Nykaa',[500,1000,2000],'both'],
            ['Westside','Westside',[500,1000,2000],'offline'],
            ['Zomato','Zomato',[250,500,1000],'offline'],
            ['Swiggy','Swiggy',[250,500,1000],'offline'],
            ['Starbucks','Starbucks',[500,1000],'offline'],
            ['Dominos','Dominos',[250,500,1000],'offline'],
            ['Amazon','Amazon',[500,1000,2000,5000],'online'],
            ['Flipkart','Flipkart',[500,1000,2000,5000],'online'],
            ['Croma','Croma',[1000,2000,5000],'both'],
            ['Vijay Sales','Vijay Sales',[1000,2000,5000],'offline'],
            ['MakeMyTrip','MakeMyTrip',[1000,2000,5000,10000],'online'],
            ['Cleartrip','Cleartrip',[1000,2500,5000],'online'],
            ['ixigo','ixigo',[500,1000,2000],'online'],
            ['Airbnb','Airbnb',[2000,5000,10000],'online'],
            ['Cult.fit','Cult.fit',[1000,2000,5000],'both'],
            ['Healthians','Healthians',[500,1000,2500],'online'],
            ['Netflix','Netflix',[199,499,649],'online'],
            ['BookMyShow','BookMyShow',[250,500,1000],'online'],
            ['Spotify','Spotify',[119,389],'online'],
            ['BigBasket','BigBasket',[250,500,1000],'offline'],
            ['Blinkit','Blinkit',[250,500,1000],'offline'],
        ];
        $extra = [
            ['Myntra','Myntra',[500,1000,2000]],['Shoppers Stop','Shoppers Stop',[500,1000,5000]],['Lifestyle','Lifestyle',[500,1000,2000]],['Trends','Trends',[500,1000]],['Pantaloons','Pantaloons',[500,1000,2000]],['Bata','Bata',[500,1000]],['Woodland','Woodland',[1000,2000]],['Levis','Levis',[1000,2000,5000]],['H&M','H&M',[500,1000,2000]],['Zara','Zara',[1000,2000,5000]],['McDonalds','McDonalds',[250,500,1000]],['KFC','KFC',[250,500,1000]],['Burger King','Burger King',[250,500]],['Pizza Hut','Pizza Hut',[250,500,1000]],['Subway','Subway',[250,500]],['Haldirams','Haldirams',[500,1000]],['Baskin Robbins','Baskin Robbins',[250,500]],['Chaayos','Chaayos',[250,500]],['Third Wave','Third Wave',[500,1000]],['Apple','Apple',[1000,2000,5000,10000]],['Samsung','Samsung',[1000,2000,5000]],['OnePlus','OnePlus',[500,1000,2000]],['Boat','Boat',[500,1000,2000]],['Noise','Noise',[500,1000]],['Sony','Sony',[1000,2000,5000]],['HP','HP',[1000,2000]],['Dell','Dell',[1000,2000,5000]],['Lenovo','Lenovo',[1000,2000]],['Uber','Uber',[500,1000,2000]],['Ola','Ola',[500,1000]],['IRCTC','IRCTC',[500,1000,2000]],['Goibibo','Goibibo',[1000,2000,5000]],['Yatra','Yatra',[1000,2000]],['FabHotels','FabHotels',[1000,2000,5000]],['Treebo','Treebo',[1000,2000]],['Apollo','Apollo',[500,1000,2000]],['1mg','1mg',[500,1000]],['PharmEasy','PharmEasy',[500,1000,2000]],['Mamaearth','Mamaearth',[500,1000]],['Plum','Plum',[500,1000,2000]],['Man Company','Man Company',[500,1000]],['Beardo','Beardo',[500,1000]],['Hotstar','Hotstar',[199,499,899]],['Prime Video','Prime Video',[199,459]],['Zee5','Zee5',[99,199,499]],['Sony Liv','Sony Liv',[199,399]],['PVR','PVR',[250,500,1000]],['INOX','INOX',[250,500,1000]],['Gaana','Gaana',[99,199]],['JioMart','JioMart',[250,500,1000,2000]],['DMart','DMart',[500,1000,2000]],['More','More',[250,500,1000]],['Star Bazaar','Star Bazaar',[500,1000]],['Natures Basket','Natures Basket',[500,1000,2000]],['Lenskart','Lenskart',[500,1000,2000]],['Titan','Titan',[1000,2000,5000]],['Fastrack','Fastrack',[500,1000,2000]],['Tanishq','Tanishq',[1000,5000,10000]],['Malabar','Malabar',[2000,5000,10000]],['Kalyan','Kalyan',[2000,5000]],['Pepperfry','Pepperfry',[1000,2000,5000]],['Home Centre','Home Centre',[1000,2000]],['Decathlon','Decathlon',[500,1000,2000]],['Puma','Puma',[1000,2000]],['Nike','Nike',[1000,2000,5000]],['Adidas','Adidas',[1000,2000,5000]],['Reebok','Reebok',[500,1000,2000]],['FirstCry','FirstCry',[500,1000]],['Hamleys','Hamleys',[500,1000,2000]],['Toys R Us','Toys R Us',[500,1000]],['Godrej','Godrej',[500,1000]],['Spencers','Spencers',[250,500,1000]],['Reliance Digital','Reliance Digital',[1000,2000,5000]],['Poorvika','Poorvika',[500,1000]],['Lot Mobiles','Lot Mobiles',[500,1000,2000]],['Sangeetha','Sangeetha',[500,1000]],['Urban Ladder','Urban Ladder',[1000,2000,5000]],['Wakefit','Wakefit',[1000,2000,5000]],
        ];

        $all = array_merge($base, $extra);
        $all = array_slice($all, 0, 100);

        $this->command->info("Creating 100 voucher products...");
        $created = [];
        foreach ($all as $i => $b) {
            $name = $b[0];
            $brand = $b[1];
            $denoms = $b[2];
            $usage = $b[3] ?? 'both';
            $pid = 'AVQ-'.strtoupper(preg_replace('/[^A-Z]/','', $brand)).'-'.str_pad($i+1,3,'0',STR_PAD_LEFT);
            $pid = substr($pid,0,20);
            $product = SendVoucherProduct::firstOrCreate(['product_id'=>$pid],[
                'name'=>$name.' Gift Card',
                'brand'=>$brand,
                'image_url'=>null,
                'currency_code'=>'INR',
                'currency_name'=>'Indian Rupee',
                'value_type'=>'fixed',
                'value_denominations'=>$denoms,
                'country_name'=>'India',
                'country_code'=>'IN',
                'countries'=>['IN'],
                'usage_type'=>$usage,
                'delivery_type'=>'realtime',
                'terms_and_conditions'=>'Valid for 12 months. Non-refundable. Redeemable only in India.',
                'redemption_instructions'=>'Enter code at checkout or show at store.',
                'expiry_and_validity'=>'12 months from issue',
                'order_quantity_limit'=>100,
                'tat_in_days'=>1,
                'fee'=>0,
                'discount'=>2.5,
                'exchange_rate'=>1,
                'low_stock_threshold'=>10,
                'is_active'=>true,
            ]);
            $created[] = $product;
        }

        $this->command->info("Creating 5000 voucher codes (encrypted via APP_KEY)...");
        $total = 0;
        while ($total < 5000) {
            foreach ($created as $product) {
                if ($total >= 5000) break;
                $denoms = $product->value_denominations;
                $denom = $denoms[array_rand($denoms)];
                $code = strtoupper(substr($product->brand,0,4)).'-'.strtoupper(Str::random(4)).'-'.rand(1000,9999).'-'.strtoupper(Str::random(3));
                $pin = rand(0,1) ? (string)rand(1000,9999) : null;
                SendVoucherCode::create([
                    'product_id'=>$product->id,
                    'denomination'=>$denom,
                    'currency_code'=>'INR',
                    'code_encrypted'=>$code,
                    'pin_encrypted'=>$pin,
                    'expiry_date'=>now()->addMonths(12),
                    'status'=>'available',
                ]);
                $total++;
                if ($total % 500 == 0) $this->command->info("  $total / 5000");
            }
        }

        $this->command->info("Simulating low stock for 10 products...");
        foreach (SendVoucherProduct::inRandomOrder()->limit(10)->get() as $lp) {
            $firstDenom = $lp->value_denominations[0] ?? 500;
            $idsToKeep = SendVoucherCode::where('product_id',$lp->id)->where('denomination',$firstDenom)->where('status','available')->limit(3)->pluck('id');
            SendVoucherCode::where('product_id',$lp->id)->where('denomination',$firstDenom)->where('status','available')->whereNotIn('id',$idsToKeep)->delete();
        }

        $this->command->info("Done! Products: ".SendVoucherProduct::count()." Codes: ".SendVoucherCode::count());
    }
}
